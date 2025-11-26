<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalisisSuelo;
use App\Models\Proyecto;
use App\Services\SoilAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class AnalisisSueloController extends Controller
{
    private $soilService;

    public function __construct(SoilAnalysisService $soilService)
    {
        $this->soilService = $soilService;
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

    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $analisis = AnalisisSuelo::with('proyecto')
            ->whereHas('proyecto', function ($query) use ($user) {
                $query->where('usuario_id', $user->id);
            })
            ->orderBy('creado_en', 'desc') // Usar creado_en en lugar de created_at
            ->get();

        return response()->json([
            'success' => true,
            'data' => $analisis,
        ]);
    }

    // En AnalisisSueloController.php - Método store()
    /**
 * Obtener un análisis específico por ID con todos sus cálculos
 */
public function show(AnalisisSuelo $analisisSuelo): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    // Verificar que el análisis pertenezca al usuario
    if ($analisisSuelo->proyecto->usuario_id !== $user->id) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    try {
        // Obtener los datos del análisis
        $datosAnalisis = $analisisSuelo->toArray();

        // Realizar el análisis inteligente con los datos existentes
        $analisisInteligente = $this->soilService->analizarSuelo($datosAnalisis);

        // Retornar la respuesta completa en el formato que espera el frontend
        return response()->json([
            'success' => true,
            'data' => $analisisSuelo->load('proyecto'),
            'calculos' => $analisisInteligente['calculos_avanzados'] ?? [],
            'recomendaciones' => $analisisInteligente['recomendaciones'] ?? [],
            'validaciones' => $analisisInteligente['validaciones'] ?? [],
            'alertas_criticas' => $analisisInteligente['alertas_criticas'] ?? [],
            'conocimiento_suelo' => $analisisInteligente['conocimiento_tipo_suelo'] ?? [],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al obtener el análisis',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

public function store(Request $request): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    $validated = $request->validate([
        'proyecto_id' => 'required|exists:proyectos,id',
        'tipo_suelo' => 'required|string',
        'clasificacion_suelo' => 'nullable|string',
        'humedad_natural' => 'nullable|numeric',
        'peso_unitario' => 'nullable|numeric',
        'densidad_bulk' => 'nullable|numeric',
        'razon_porosidad' => 'nullable|numeric',
        'angulo_friccion' => 'nullable|numeric',
        'cohesion' => 'nullable|numeric',
        'capacidad_portante' => 'required|numeric',
        'riesgo_licuefaccion' => 'nullable|in:bajo,medio,alto',
        'profundidad_sondeo' => 'nullable|numeric',
        'nivel_freatico' => 'nullable|numeric',
    ]);

    $proyecto = Proyecto::findOrFail($validated['proyecto_id']);
    if ($proyecto->usuario_id !== $user->id) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    try {
        // ✅ SOLUCIÓN: Convertir strings vacíos a null
        $datosLimpios = array_map(function($value) {
            return ($value === '' || $value === '0') ? null : $value;
        }, $validated);

        // Usar el servicio inteligente
        $analisisInteligente = $this->soilService->analizarSuelo($datosLimpios);

        if (!$analisisInteligente['valido']) {
            return response()->json([
                'error' => $analisisInteligente['mensaje']
            ], 400);
        }

        // Crear el registro
        $analisis = AnalisisSuelo::create(array_merge($datosLimpios, [
            'creado_por' => $user->id,
            'actualizado_por' => $user->id,
            'calidad_analisis' => $analisisInteligente['calidad_analisis'],
            'recomendaciones_ia' => json_encode($analisisInteligente['recomendaciones']),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Análisis creado exitosamente',
            'data' => $analisis->load('proyecto'),
            'calculos' => $analisisInteligente['calculos_avanzados'],
            'recomendaciones' => $analisisInteligente['recomendaciones'],
            'validaciones' => $analisisInteligente['validaciones'],
            'alertas_criticas' => $analisisInteligente['alertas_criticas'],
            'conocimiento_suelo' => $analisisInteligente['conocimiento_tipo_suelo'],
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al procesar análisis',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

    public function update(Request $request, AnalisisSuelo $analisisSuelo): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    if ($analisisSuelo->proyecto->usuario_id !== $user->id) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    $validated = $request->validate([
        'tipo_suelo' => 'nullable|string',
        'clasificacion_suelo' => 'nullable|string',
        'humedad_natural' => 'nullable|numeric',
        'peso_unitario' => 'nullable|numeric',
        'densidad_bulk' => 'nullable|numeric',
        'razon_porosidad' => 'nullable|numeric',
        'angulo_friccion' => 'nullable|numeric',
        'cohesion' => 'nullable|numeric',
        'capacidad_portante' => 'nullable|numeric',
        'riesgo_licuefaccion' => 'nullable|in:bajo,medio,alto',
        'profundidad_sondeo' => 'nullable|numeric',
        'nivel_freatico' => 'nullable|numeric',
    ]);

    try {
        // ✅ SOLUCIÓN: Convertir strings vacíos a null
        $validadoLimpio = array_map(function($value) {
            return ($value === '' || $value === '0') ? null : $value;
        }, $validated);

        // Mezclar datos existentes con los nuevos
        $datosCompletos = array_merge($analisisSuelo->toArray(), $validadoLimpio);

        // Análisis inteligente
        $analisisInteligente = $this->soilService->analizarSuelo($datosCompletos);

        $validadoLimpio['actualizado_por'] = $user->id;
        $validadoLimpio['calidad_analisis'] = $analisisInteligente['calidad_analisis'];
        $validadoLimpio['recomendaciones_ia'] = json_encode($analisisInteligente['recomendaciones']);

        $analisisSuelo->update($validadoLimpio);

        return response()->json([
            'success' => true,
            'message' => 'Análisis actualizado exitosamente',
            'data' => $analisisSuelo->load('proyecto'),
            'analisis_inteligente' => [
                'conocimiento_suelo' => $analisisInteligente['conocimiento_tipo_suelo'],
                'validaciones' => $analisisInteligente['validaciones'],
                'calculos_avanzados' => $analisisInteligente['calculos_avanzados'],
                'recomendaciones' => $analisisInteligente['recomendaciones'],
                'alertas_criticas' => $analisisInteligente['alertas_criticas'],
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al actualizar análisis',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

    public function destroy(AnalisisSuelo $analisisSuelo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        if ($analisisSuelo->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $analisisSuelo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Análisis eliminado exitosamente',
        ]);
    }

   // En AnalisisSueloController.php - Método procesarArchivo()

public function procesarArchivo(Request $request): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    $request->validate([
        'archivo' => 'required|file|mimes:json,xlsx,xls|max:5120',
        'proyecto_id' => 'required|exists:proyectos,id',
    ]);

    try {
        $proyecto = Proyecto::findOrFail($request->proyecto_id);
        if ($proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $archivo = $request->file('archivo');
        $datos = $this->extraerDatos($archivo);

        if (empty($datos)) {
            return response()->json([
                'error' => 'No se encontraron datos válidos en el archivo'
            ], 400);
        }

        $datos['proyecto_id'] = $request->proyecto_id;

        // Análisis inteligente
        $analisisInteligente = $this->soilService->analizarSuelo($datos);

        // ✅ SOLUCIÓN: Usar array_merge con valores por defecto para TODOS los campos
        $datosAnalisis = array_merge([
            'proyecto_id' => $request->proyecto_id,
            'tipo_suelo' => '',
            'clasificacion_suelo' => null,
            'humedad_natural' => null,
            'peso_unitario' => null,
            'densidad_bulk' => null,
            'razon_porosidad' => null,
            'angulo_friccion' => null,
            'cohesion' => null,
            'capacidad_portante' => 0,
            'riesgo_licuefaccion' => 'bajo',
            'profundidad_sondeo' => null,
            'nivel_freatico' => null,
            'calidad_analisis' => null,
            'recomendaciones_ia' => json_encode([]),
            'creado_por' => $user->id,
            'actualizado_por' => $user->id,
        ], array_filter($datos, function($v) { 
            return $v !== null && $v !== ''; 
        }));

        $analisis = AnalisisSuelo::create($datosAnalisis);

        return response()->json([
            'success' => true,
            'message' => 'Archivo procesado exitosamente',
            'data' => $analisis->load('proyecto'),
            'analisis_inteligente' => [
                'conocimiento_suelo' => $analisisInteligente['conocimiento_tipo_suelo'] ?? null,
                'validaciones' => $analisisInteligente['validaciones'] ?? [],
                'calculos_avanzados' => $analisisInteligente['calculos_avanzados'] ?? [],
                'recomendaciones' => $analisisInteligente['recomendaciones'] ?? [],
                'alertas_criticas' => $analisisInteligente['alertas_criticas'] ?? [],
            ]
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al procesar archivo',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

    /**
     * NUEVO: Endpoint para obtener información de tipos de suelo
     */
    public function obtenerTiposSuelo(): JsonResponse
    {
        try {
            $tipos = $this->soilService->obtenerTodosTiposSuelo();

            return response()->json([
                'success' => true,
                'data' => $tipos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener tipos de suelo',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NUEVO: Endpoint para obtener información detallada de un tipo de suelo
     */
    public function obtenerInfoTipoSuelo($tipoSuelo): JsonResponse
    {
        try {
            $info = $this->soilService->obtenerInfoTipoSuelo($tipoSuelo);

            if (!$info) {
                return response()->json([
                    'error' => 'Tipo de suelo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener información',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
private function extraerDatos($archivo)
{
    $extension = $archivo->getClientOriginalExtension();
    $datosDefault = [
        'tipo_suelo' => '',
        'clasificacion_suelo' => null,
        'humedad_natural' => null,
        'peso_unitario' => null,
        'densidad_bulk' => null,
        'razon_porosidad' => null,
        'angulo_friccion' => null,
        'cohesion' => null,
        'capacidad_portante' => null,
        'riesgo_licuefaccion' => 'bajo',
        'profundidad_sondeo' => null,
        'nivel_freatico' => null,
    ];

    if ($extension === 'json') {
        $contenido = file_get_contents($archivo->getRealPath());
        $decoded = json_decode($contenido, true);
        // ✅ Fusionar con valores por defecto
        return $decoded ? array_merge($datosDefault, $decoded) : $datosDefault;
    }

    if (in_array($extension, ['xlsx', 'xls'])) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            $firstRow = true;
            foreach ($sheet->getRowIterator() as $row) {
                if ($firstRow) {
                    $firstRow = false;
                    continue;
                }

                $cellIterator = $row->getCellIterator();
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                if (!empty(array_filter($rowData))) {
                    // ✅ Asignar explícitamente todos los campos
                    return array_merge($datosDefault, [
                        'tipo_suelo' => $rowData[0] ?? '',
                        'clasificacion_suelo' => $rowData[1] ?? null,
                        'humedad_natural' => $rowData[2] ?? null,
                        'peso_unitario' => $rowData[3] ?? null,
                        'densidad_bulk' => $rowData[4] ?? null,
                        'razon_porosidad' => $rowData[5] ?? null,
                        'angulo_friccion' => $rowData[6] ?? null,
                        'cohesion' => $rowData[7] ?? null,
                        'capacidad_portante' => $rowData[8] ?? null,
                        'riesgo_licuefaccion' => $rowData[9] ?? 'bajo',
                        'profundidad_sondeo' => $rowData[10] ?? null,
                        'nivel_freatico' => $rowData[11] ?? null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            return $datosDefault;
        }
    }

    return $datosDefault;
}

/**
 * Obtener análisis de suelo disponibles para un proyecto específico
 */
public function obtenerAnalisisSueloDisponibles($proyectoId): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    // Verificar que el proyecto pertenece al usuario
    $proyecto = Proyecto::findOrFail($proyectoId);
    if ($proyecto->usuario_id !== $user->id) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    // Obtener análisis de suelo del proyecto
    $analisisSuelo = AnalisisSuelo::where('proyecto_id', $proyectoId)
        ->orderBy('creado_en', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $analisisSuelo,
    ]);
}
}

