<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cimentacion;
use App\Models\Proyecto;
use App\Models\AnalisisSuelo;
use App\Services\FoundationAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class CimentacionController extends Controller
{
    private $foundationService;

    public function __construct(FoundationAnalysisService $foundationService)
    {
        $this->foundationService = $foundationService;
    }

    protected function getUser()
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Listar todas las cimentaciones del usuario
     */
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $cimentaciones = Cimentacion::with(['proyecto', 'analisisSuelo'])
            ->whereHas('proyecto', function ($query) use ($user) {
                $query->where('usuario_id', $user->id);
            })
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cimentaciones,
        ]);
    }

    /**
     * Crear nueva cimentación
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validated = $request->validate([
            'proyecto_id' => 'required|exists:proyectos,id',
            'analisis_suelo_id' => 'required|exists:analisis_suelo,id',
            'tipo_cimentacion' => 'required|string',
            'subtipo' => 'nullable|string',
            'profundidad' => 'required|numeric|min:0',
            'ancho' => 'nullable|numeric|min:0',
            'largo' => 'nullable|numeric|min:0',
            'area_apoyo' => 'nullable|numeric|min:0',
            'carga_diseno_total' => 'required|numeric|min:0',
            'presion_portante_admisible' => 'nullable|numeric',
            'factor_seguridad' => 'nullable|numeric',
            'asentamiento_mm' => 'nullable|numeric',
            'grado_concreto' => 'nullable|string',
            'volumen_concreto' => 'nullable|numeric',
            'porcentaje_acero' => 'nullable|numeric',
            'longitud_pilote' => 'nullable|numeric',
            'diametro_pilote' => 'nullable|numeric',
            'numero_pilotes' => 'nullable|integer',
        ]);

        // Verificar que el proyecto pertenece al usuario
        $proyecto = Proyecto::findOrFail($validated['proyecto_id']);
        if ($proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // ✅ FIX: Convertir a enteros antes de comparar
        $analisisSuelo = AnalisisSuelo::findOrFail($validated['analisis_suelo_id']);
        if ((int)$analisisSuelo->proyecto_id !== (int)$validated['proyecto_id']) {
            return response()->json([
                'error' => 'El análisis de suelo no pertenece al proyecto seleccionado',
                'debug' => [
                    'analisis_proyecto_id' => $analisisSuelo->proyecto_id,
                    'analisis_proyecto_id_tipo' => gettype($analisisSuelo->proyecto_id),
                    'proyecto_id_enviado' => $validated['proyecto_id'],
                    'proyecto_id_enviado_tipo' => gettype($validated['proyecto_id'])
                ]
            ], 400);
        }

        try {
            // Limpiar datos (convertir strings vacíos a null)
            $datosLimpios = array_map(function($value) {
                return ($value === '' || $value === '0') ? null : $value;
            }, $validated);

            // Análisis inteligente
            $analisisInteligente = $this->foundationService->analizarCimentacion($datosLimpios, $analisisSuelo);

            if (!$analisisInteligente['valido']) {
                return response()->json([
                    'error' => 'Diseño de cimentación no válido',
                    'validaciones' => $analisisInteligente['validaciones'],
                    'alertas' => $analisisInteligente['alertas_criticas'],
                ], 400);
            }

            // Usar datos calculados del servicio
            $datosFinales = array_merge($datosLimpios, $analisisInteligente['datos_calculados'], [
                'creado_por' => $user->id,
                'actualizado_por' => $user->id,
                'esta_optimizada' => $analisisInteligente['optimizacion']['esta_optimizada'],
                'potencial_optimizacion_costo' => $analisisInteligente['optimizacion']['potencial_ahorro'],
                'recomendaciones_ia' => json_encode($analisisInteligente['recomendaciones']),
            ]);

            $cimentacion = Cimentacion::create($datosFinales);

            return response()->json([
                'success' => true,
                'message' => 'Cimentación creada exitosamente',
                'data' => $cimentacion->load(['proyecto', 'analisisSuelo']),
                'validaciones' => $analisisInteligente['validaciones'],
                'recomendaciones' => $analisisInteligente['recomendaciones'],
                'optimizacion' => $analisisInteligente['optimizacion'],
                'alertas_criticas' => $analisisInteligente['alertas_criticas'],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear cimentación',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una cimentación específica con análisis completo
     */
/**
 * Mostrar una cimentación específica con análisis completo
 */
public function show($id): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    // ✅ FIX: Cargar la cimentación CON sus relaciones desde el inicio
    $cimentacion = Cimentacion::with(['proyecto', 'analisisSuelo'])
        ->findOrFail($id);

    // Verificar que la relación proyecto existe
    if (!$cimentacion->proyecto) {
        return response()->json([
            'error' => 'La cimentación no tiene un proyecto asociado válido',
            'debug' => [
                'cimentacion_id' => $cimentacion->id,
                'proyecto_id' => $cimentacion->proyecto_id
            ]
        ], 404);
    }

    if ($cimentacion->proyecto->usuario_id !== $user->id) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    // Versión temporal sin análisis inteligente
    return response()->json([
        'success' => true,
        'data' => $cimentacion,
        'validaciones' => [
            'factor_seguridad' => [
                'valido' => ($cimentacion->factor_seguridad ?? 0) >= 1.0,
                'mensaje' => 'Factor de seguridad: ' . ($cimentacion->factor_seguridad ?? 'N/A'),
                'valor' => $cimentacion->factor_seguridad
            ]
        ],
        'recomendaciones' => [
            'diseno' => ['Revisar diseño estructural según normativa vigente'],
            'construccion' => ['Seguir especificaciones técnicas del proyecto'],
            'control_calidad' => ['Realizar pruebas de laboratorio periódicas']
        ],
        'optimizacion' => [
            'esta_optimizada' => $cimentacion->esta_optimizada ?? false,
            'potencial_ahorro' => $cimentacion->potencial_optimizacion_costo ?? 0,
            'sugerencias' => []
        ],
        'alertas_criticas' => [],
    ], 200);
}
    /**
     * Actualizar cimentación
     */
    public function update(Request $request, Cimentacion $cimentacion): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // ✅ FIX: Cargar la relación antes de acceder a ella
        $cimentacion->load('proyecto');

        if ($cimentacion->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'tipo_cimentacion' => 'nullable|string',
            'subtipo' => 'nullable|string',
            'profundidad' => 'nullable|numeric|min:0',
            'ancho' => 'nullable|numeric|min:0',
            'largo' => 'nullable|numeric|min:0',
            'area_apoyo' => 'nullable|numeric|min:0',
            'carga_diseno_total' => 'nullable|numeric|min:0',
            'presion_portante_admisible' => 'nullable|numeric',
            'factor_seguridad' => 'nullable|numeric',
            'asentamiento_mm' => 'nullable|numeric',
            'grado_concreto' => 'nullable|string',
            'volumen_concreto' => 'nullable|numeric',
            'porcentaje_acero' => 'nullable|numeric',
            'longitud_pilote' => 'nullable|numeric',
            'diametro_pilote' => 'nullable|numeric',
            'numero_pilotes' => 'nullable|integer',
        ]);

        try {
            // Limpiar datos
            $validadoLimpio = array_map(function($value) {
                return ($value === '' || $value === '0') ? null : $value;
            }, $validated);

            // Mezclar datos existentes con nuevos
            $datosCompletos = array_merge($cimentacion->toArray(), $validadoLimpio);

            // Análisis inteligente
            $analisisInteligente = $this->foundationService->analizarCimentacion(
                $datosCompletos, 
                $cimentacion->analisisSuelo
            );

            $datosFinales = array_merge($validadoLimpio, [
                'actualizado_por' => $user->id,
                'esta_optimizada' => $analisisInteligente['optimizacion']['esta_optimizada'],
                'potencial_optimizacion_costo' => $analisisInteligente['optimizacion']['potencial_ahorro'],
                'recomendaciones_ia' => json_encode($analisisInteligente['recomendaciones']),
            ]);

            $cimentacion->update($datosFinales);

            return response()->json([
                'success' => true,
                'message' => 'Cimentación actualizada exitosamente',
                'data' => $cimentacion->load(['proyecto', 'analisisSuelo']),
                'analisis_inteligente' => [
                    'validaciones' => $analisisInteligente['validaciones'],
                    'recomendaciones' => $analisisInteligente['recomendaciones'],
                    'optimizacion' => $analisisInteligente['optimizacion'],
                    'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar cimentación',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cimentación
     */
    public function destroy(Cimentacion $cimentacion): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // ✅ FIX: Cargar la relación antes de acceder a ella
        $cimentacion->load('proyecto');

        if ($cimentacion->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $cimentacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cimentación eliminada exitosamente',
        ]);
    }

    /**
     * Obtener análisis de suelo disponibles para un proyecto
     */
    public function obtenerAnalisisSueloDisponibles($proyectoId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::findOrFail($proyectoId);
        if ($proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $analisisSuelo = AnalisisSuelo::where('proyecto_id', $proyectoId)
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $analisisSuelo,
        ]);
    }

    public function obtenerCimentacionesDisponibles($proyecto): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Buscar el proyecto
        $proyectoModel = Proyecto::findOrFail($proyecto);
        
        // Verificar que pertenece al usuario
        if ($proyectoModel->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Obtener cimentaciones del proyecto
        $cimentaciones = Cimentacion::where('proyecto_id', $proyecto)
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cimentaciones,
        ]);
    }
}
