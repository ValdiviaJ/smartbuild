<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisenoEstructural;
use App\Models\Proyecto;
use App\Models\Cimentacion;
use App\Services\StructuralAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class DisenoEstructuralController extends Controller
{
    private $structuralService;

    public function __construct(StructuralAnalysisService $structuralService)
    {
        $this->structuralService = $structuralService;
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
     * Listar todos los diseños estructurales del usuario
     */
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $diseños = DisenoEstructural::with(['proyecto', 'cimentacion'])
            ->whereHas('proyecto', function ($query) use ($user) {
                $query->where('usuario_id', $user->id);
            })
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $diseños,
        ]);
    }

    /**
     * Crear nuevo diseño estructural
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validated = $request->validate([
            'proyecto_id' => 'required|exists:proyectos,id',
            'cimentacion_id' => 'nullable|exists:cimentaciones,id',
            'tipo_estructura' => 'required|string',
            'codigo_diseno' => 'nullable|string',
            'carga_muerta' => 'required|numeric|min:0',
            'carga_viva' => 'required|numeric|min:0',
            'carga_viento' => 'nullable|numeric|min:0',
            'zona_sismica' => 'nullable|string',
            'factor_sismico' => 'nullable|numeric|min:0',
            'cantidad_vigas' => 'nullable|integer|min:0',
            'luz_viga' => 'nullable|numeric|min:0',
            'peralte_viga' => 'nullable|numeric|min:0',
            'ancho_viga' => 'nullable|numeric|min:0',
            'acero_superior_viga' => 'nullable|string',
            'acero_inferior_viga' => 'nullable|string',
            'cantidad_columnas' => 'nullable|integer|min:0',
            'altura_columna' => 'nullable|numeric|min:0',
            'seccion_columna' => 'nullable|string',
            'dimension_columna_1' => 'nullable|numeric|min:0',
            'dimension_columna_2' => 'nullable|numeric|min:0',
            'acero_columna' => 'nullable|string',
            'cantidad_losas' => 'nullable|integer|min:0',
            'espesor_losa' => 'nullable|numeric|min:0',
            'acero_losa_x' => 'nullable|string',
            'acero_losa_y' => 'nullable|string',
            'tipo_losa' => 'nullable|string',
            'momento_maximo' => 'nullable|numeric|min:0',
            'cortante_maximo' => 'nullable|numeric|min:0',
            'flecha_maxima_mm' => 'nullable|numeric|min:0',
            'esfuerzo_compresion' => 'nullable|numeric|min:0',
        ]);

        // Verificar que el proyecto pertenece al usuario
        $proyecto = Proyecto::findOrFail($validated['proyecto_id']);
        if ($proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Verificar cimentación si se proporciona
        $cimentacion = null;
        if (isset($validated['cimentacion_id']) && $validated['cimentacion_id']) {
            $cimentacion = Cimentacion::findOrFail($validated['cimentacion_id']);
            if ((int)$cimentacion->proyecto_id !== (int)$validated['proyecto_id']) {
                return response()->json([
                    'error' => 'La cimentación no pertenece al proyecto seleccionado',
                ], 400);
            }
        }

        try {
            // Limpiar datos
            $datosLimpios = array_map(function($value) {
                return ($value === '' || $value === '0') ? null : $value;
            }, $validated);

            // Análisis inteligente
            $analisisInteligente = $this->structuralService->analizarDiseño($datosLimpios, $cimentacion);

            if (!$analisisInteligente['valido']) {
                return response()->json([
                    'error' => 'Diseño estructural no válido según normas',
                    'validaciones' => $analisisInteligente['validaciones'],
                    'alertas' => $analisisInteligente['alertas_criticas'],
                ], 400);
            }

            // Preparar datos finales
            $datosFinales = array_merge($datosLimpios, [
                'optimizacion_ia_aplicada' => $analisisInteligente['optimizacion']['esta_optimizada'],
                'estado_diseno' => 'completado',
                'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
                'creado_por' => $user->id,
                'actualizado_por' => $user->id,
            ]);

            $diseño = DisenoEstructural::create($datosFinales);

            return response()->json([
                'success' => true,
                'message' => 'Diseño estructural creado exitosamente',
                'data' => $diseño->load(['proyecto', 'cimentacion']),
                'validaciones' => $analisisInteligente['validaciones'],
                'recomendaciones' => $analisisInteligente['recomendaciones'],
                'optimizacion' => $analisisInteligente['optimizacion'],
                'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear diseño estructural',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un diseño estructural específico
     */
    public function show(DisenoEstructural $diseño): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        if ($diseño->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $diseño->load(['proyecto', 'cimentacion']),
        ]);
    }

    /**
     * Actualizar diseño estructural
     */
    public function update(Request $request, DisenoEstructural $diseño): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        if ($diseño->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'tipo_estructura' => 'nullable|string',
            'codigo_diseno' => 'nullable|string',
            'carga_muerta' => 'nullable|numeric|min:0',
            'carga_viva' => 'nullable|numeric|min:0',
            'carga_viento' => 'nullable|numeric|min:0',
            'zona_sismica' => 'nullable|string',
            'factor_sismico' => 'nullable|numeric|min:0',
            'cantidad_vigas' => 'nullable|integer|min:0',
            'luz_viga' => 'nullable|numeric|min:0',
            'peralte_viga' => 'nullable|numeric|min:0',
            'ancho_viga' => 'nullable|numeric|min:0',
            'acero_superior_viga' => 'nullable|string',
            'acero_inferior_viga' => 'nullable|string',
            'cantidad_columnas' => 'nullable|integer|min:0',
            'altura_columna' => 'nullable|numeric|min:0',
            'seccion_columna' => 'nullable|string',
            'dimension_columna_1' => 'nullable|numeric|min:0',
            'dimension_columna_2' => 'nullable|numeric|min:0',
            'acero_columna' => 'nullable|string',
            'cantidad_losas' => 'nullable|integer|min:0',
            'espesor_losa' => 'nullable|numeric|min:0',
            'acero_losa_x' => 'nullable|string',
            'acero_losa_y' => 'nullable|string',
            'tipo_losa' => 'nullable|string',
            'momento_maximo' => 'nullable|numeric|min:0',
            'cortante_maximo' => 'nullable|numeric|min:0',
            'flecha_maxima_mm' => 'nullable|numeric|min:0',
            'esfuerzo_compresion' => 'nullable|numeric|min:0',
        ]);

        try {
            // Limpiar datos
            $validadoLimpio = array_map(function($value) {
                return ($value === '' || $value === '0') ? null : $value;
            }, $validated);

            // Mezclar datos existentes con nuevos
            $datosCompletos = array_merge($diseño->toArray(), $validadoLimpio);

            // Análisis inteligente
            $analisisInteligente = $this->structuralService->analizarDiseño(
                $datosCompletos,
                $diseño->cimentacion
            );

            $datosFinales = array_merge($validadoLimpio, [
                'actualizado_por' => $user->id,
                'optimizacion_ia_aplicada' => $analisisInteligente['optimizacion']['esta_optimizada'],
                'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
            ]);

            $diseño->update($datosFinales);

            return response()->json([
                'success' => true,
                'message' => 'Diseño estructural actualizado exitosamente',
                'data' => $diseño->load(['proyecto', 'cimentacion']),
                'analisis_inteligente' => [
                    'validaciones' => $analisisInteligente['validaciones'],
                    'recomendaciones' => $analisisInteligente['recomendaciones'],
                    'optimizacion' => $analisisInteligente['optimizacion'],
                    'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                    'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar diseño estructural',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar diseño estructural
     */
    public function destroy(DisenoEstructural $diseño): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        if ($diseño->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $diseño->delete();

        return response()->json([
            'success' => true,
            'message' => 'Diseño estructural eliminado exitosamente',
        ]);
    }

    /**
     * Obtener cimentaciones disponibles para un proyecto
     */
    public function obtenerCimentacionesDisponibles($proyectoId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::findOrFail($proyectoId);
        if ($proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $cimentaciones = Cimentacion::where('proyecto_id', $proyectoId)
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cimentaciones,
        ]);
    }

    /**
     * Re-analizar un diseño estructural existente para obtener detalles
     */
    public function analizar($id): JsonResponse
    {
        $diseño = DisenoEstructural::findOrFail($id);
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        if ($diseño->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $datos = $diseño->toArray();

            $analisisInteligente = $this->structuralService->analizarDiseño(
                $datos,
                $diseño->cimentacion
            );

            return response()->json([
                'success' => true,
                'data' => $diseño,
                'analisis' => [
                    'validaciones' => $analisisInteligente['validaciones'],
                    'recomendaciones' => $analisisInteligente['recomendaciones'],
                    'optimizacion' => $analisisInteligente['optimizacion'],
                    'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                    'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al analizar diseño',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
