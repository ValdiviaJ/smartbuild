<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\FoundationAnalysisService;
use App\Models\AnalisisSuelo;

class CalculoRapidoController extends Controller
{
    protected $foundationService;
    protected $structuralService;

    public function __construct(FoundationAnalysisService $foundationService, \App\Services\StructuralAnalysisService $structuralService)
    {
        $this->foundationService = $foundationService;
        $this->structuralService = $structuralService;
    }

    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            $tipo = $request->query('tipo', 'suelo'); // Default a suelo por compatibilidad
            
            $calculos = DB::table('calculos_rapidos')
                ->where('usuario_id', $userId)
                ->where('tipo_calculo', $tipo)
                ->orderBy('creado_en', 'desc')
                ->get();

            // Decodificar JSONs para el frontend
            $calculos = $calculos->map(function ($calc) {
                $calc->datos_entrada = json_decode($calc->datos_entrada);
                $calc->datos_salida = json_decode($calc->datos_salida);
                return $calc;
            });

            return response()->json([
                'success' => true,
                'data' => $calculos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener cálculos rápidos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            $datos = $request->all();
            $tipoCalculo = $request->input('tipo_calculo', 'suelo'); // Default a suelo

            $datosSalida = [];

            // Validar datos mínimos según el tipo
            if ($tipoCalculo === 'suelo') {
                if (empty($datos['tipo_suelo']) || empty($datos['capacidad_portante'])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Faltan datos requeridos para el cálculo de suelo'
                    ], 400);
                }
                
                // Simulación simple para suelo (ya que no hay servicio complejo aún)
                $datosSalida = [
                    'data' => $datos,
                    'recomendaciones' => [
                        'tipo' => 'informativo',
                        'prioridad' => 'media',
                        'sugerencias' => ['Cálculo rápido de suelo guardado correctamente']
                    ]
                ];

            } elseif ($tipoCalculo === 'cimentacion') {
                if (empty($datos['tipo_cimentacion']) || empty($datos['profundidad']) || empty($datos['carga_diseno_total'])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Faltan datos requeridos para el cálculo de cimentación'
                    ], 400);
                }

                // Crear un objeto AnalisisSuelo temporal para el servicio
                // Usamos valores por defecto seguros o los que vengan en el request si se implementan campos extra
                $analisisSueloTemp = new AnalisisSuelo([
                    'tipo_suelo' => $datos['tipo_suelo_manual'] ?? 'No especificado',
                    'capacidad_portante' => $datos['presion_portante_admisible'] ?? 0, // Usamos la presión admisible como capacidad si no hay otra
                    'nivel_freatico' => 0,
                    'riesgo_licuefaccion' => 'bajo',
                ]);

                // Realizar análisis usando el servicio existente
                // Usamos $datos directamente para evitar problemas con array_map y tipos
                $analisisInteligente = $this->foundationService->analizarCimentacion($datos, $analisisSueloTemp);

                // DEBUG: Forzar una recomendación para verificar visualización
                if (empty($analisisInteligente['recomendaciones']['diseno'])) {
                    $analisisInteligente['recomendaciones']['diseno'][] = "Nota: Análisis generado para " . ($datos['tipo_cimentacion'] ?? 'Desconocido');
                }

                // Estructurar la salida igual que en CimentacionController
                $datosSalida = [
                    'data' => array_merge($datos, $analisisInteligente['datos_calculados']),
                    'validaciones' => $analisisInteligente['validaciones'],
                    'recomendaciones' => $analisisInteligente['recomendaciones'],
                    'optimizacion' => $analisisInteligente['optimizacion'],
                    'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                ];

            } elseif ($tipoCalculo === 'estructural') {
                if (empty($datos['tipo_estructura']) || empty($datos['carga_muerta']) || empty($datos['carga_viva'])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Faltan datos requeridos para el diseño estructural'
                    ], 400);
                }

                // Realizar análisis usando el servicio existente
                // No necesitamos cimentación para el cálculo rápido aislado, pasamos null
                $analisisInteligente = $this->structuralService->analizarDiseño($datos, null);

                // Estructurar la salida
                $datosSalida = [
                    'data' => array_merge($datos, $analisisInteligente['datos_calculados']),
                    'validaciones' => $analisisInteligente['validaciones'],
                    'recomendaciones' => $analisisInteligente['recomendaciones'],
                    'optimizacion' => $analisisInteligente['optimizacion'],
                    'alertas_criticas' => $analisisInteligente['alertas_criticas'],
                    'cumplimiento_norma' => $analisisInteligente['cumplimiento_norma'],
                ];
            } elseif ($tipoCalculo === 'materiales') {
                if (empty($datos['nombre']) || empty($datos['categoria']) || empty($datos['precio_base'])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Faltan datos requeridos para el material'
                    ], 400);
                }

                // Para materiales, el "cálculo" es básicamente validar y formatear
                // No hay un servicio complejo de análisis por ahora
                $datosSalida = [
                    'data' => $datos,
                    'validaciones' => [
                        'es_valido' => true,
                        'mensajes' => ['Material registrado correctamente en modo borrador']
                    ],
                    'recomendaciones' => [
                        'general' => ['Este material está guardado como cálculo rápido/borrador y no afecta el inventario principal.']
                    ]
                ];
            }

            $datosEntrada = json_encode($datos);
            $datosSalidaJson = json_encode($datosSalida);

            $id = DB::table('calculos_rapidos')->insertGetId([
                'usuario_id' => $userId,
                'tipo_calculo' => $tipoCalculo,
                'datos_entrada' => $datosEntrada,
                'datos_salida' => $datosSalidaJson,
                'es_temporal' => false,
                'creado_en' => now(),
                'actualizado_en' => now()
            ]);

            $nuevoCalculo = DB::table('calculos_rapidos')->where('id', $id)->first();
            $nuevoCalculo->datos_entrada = json_decode($nuevoCalculo->datos_entrada);
            $nuevoCalculo->datos_salida = json_decode($nuevoCalculo->datos_salida);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo rápido guardado y procesado',
                'data' => $nuevoCalculo
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar cálculo rápido: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $userId = Auth::id();
            
            $deleted = DB::table('calculos_rapidos')
                ->where('id', $id)
                ->where('usuario_id', $userId)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cálculo eliminado correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Cálculo no encontrado o no autorizado'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al eliminar cálculo: ' . $e->getMessage()
            ], 500);
        }
    }
}
