<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstimacionCosto;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class EstimacionCostoController extends Controller
{
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

    public function index()
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        try {
            $estimaciones = EstimacionCosto::with(['proyecto'])
                ->whereHas('proyecto', fn($q) => $q->where('usuario_id', $user->id))
                ->orderBy('creado_en', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $estimaciones]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        try {
            $validated = $request->validate([
                'proyecto_id' => 'required|integer|exists:proyectos,id',
                'tipo_estimacion' => 'required|string',
                'costo_materiales' => 'required|numeric|min:0',
                'costo_mano_obra' => 'required|numeric|min:0',
                'alquiler_equipos' => 'nullable|numeric|min:0',
                'gastos_generales' => 'nullable|numeric|min:0',
                'contingencia' => 'nullable|numeric|min:0',
                'impuestos' => 'nullable|numeric|min:0',
                'moneda' => 'required|string',
                'diseño_estructural_id' => 'nullable|integer|exists:diseños_estructurales,id',
                'tasa_cambio' => 'nullable|numeric',
                'sugerencias_optimizacion_costo' => 'nullable|string',
                'ahorros_estimados' => 'nullable|numeric|min:0',
                'optimizacion_aplicada' => 'nullable|boolean',
            ]);

            // Verificar que proyecto pertenece al usuario
            $proyecto = Proyecto::findOrFail($validated['proyecto_id']);
            if ($proyecto->usuario_id !== $user->id) {
                return response()->json(['error' => 'No autorizado para este proyecto'], 403);
            }

            // Convertir a números flotantes
            $costoMateriales = (float)($validated['costo_materiales'] ?? 0);
            $costoManoObra = (float)($validated['costo_mano_obra'] ?? 0);
            $alquilerEquipos = (float)($validated['alquiler_equipos'] ?? 0);
            $gastosGenerales = (float)($validated['gastos_generales'] ?? 0);
            $contingencia = (float)($validated['contingencia'] ?? 0);

            // Calcular subtotal
            $subtotal = $costoMateriales + $costoManoObra + $alquilerEquipos + $gastosGenerales + $contingencia;

            $estimacion = EstimacionCosto::create([
                'proyecto_id' => $validated['proyecto_id'],
                'diseño_estructural_id' => $validated['diseño_estructural_id'] ?? null,
                'tipo_estimacion' => $validated['tipo_estimacion'],
                'costo_materiales' => $costoMateriales,
                'costo_mano_obra' => $costoManoObra,
                'alquiler_equipos' => $alquilerEquipos,
                'gastos_generales' => $gastosGenerales,
                'contingencia' => $contingencia,
                'subtotal' => $subtotal,
                'impuestos' => (float)($validated['impuestos'] ?? 0),
                'moneda' => $validated['moneda'],
                'tasa_cambio' => $validated['tasa_cambio'] ?? null,
                'sugerencias_optimizacion_costo' => $validated['sugerencias_optimizacion_costo'] ?? null,
                'ahorros_estimados' => (float)($validated['ahorros_estimados'] ?? 0),
                'optimizacion_aplicada' => (bool)($validated['optimizacion_aplicada'] ?? false),
                'creado_por' => $user->id,
            ]);

            return response()->json(['success' => true, 'data' => $estimacion->load('proyecto')], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validación fallida', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(EstimacionCosto $estimacion)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        if ($estimacion->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json(['success' => true, 'data' => $estimacion->load('proyecto')]);
    }

    public function update(Request $request, EstimacionCosto $estimacion)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        if ($estimacion->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $validated = $request->validate([
                'tipo_estimacion' => 'sometimes|string',
                'costo_materiales' => 'sometimes|numeric|min:0',
                'costo_mano_obra' => 'sometimes|numeric|min:0',
                'alquiler_equipos' => 'sometimes|numeric|min:0',
                'gastos_generales' => 'sometimes|numeric|min:0',
                'contingencia' => 'sometimes|numeric|min:0',
                'impuestos' => 'sometimes|numeric|min:0',
                'moneda' => 'sometimes|string',
                'tasa_cambio' => 'sometimes|numeric',
                'sugerencias_optimizacion_costo' => 'sometimes|string',
                'ahorros_estimados' => 'sometimes|numeric|min:0',
                'optimizacion_aplicada' => 'sometimes|boolean',
            ]);

            // Recalcular subtotal si hay cambios
            if (isset($validated['costo_materiales']) || isset($validated['costo_mano_obra']) || 
                isset($validated['alquiler_equipos']) || isset($validated['gastos_generales']) || 
                isset($validated['contingencia'])) {
                
                $subtotal = (float)($validated['costo_materiales'] ?? $estimacion->costo_materiales) +
                           (float)($validated['costo_mano_obra'] ?? $estimacion->costo_mano_obra) +
                           (float)($validated['alquiler_equipos'] ?? $estimacion->alquiler_equipos) +
                           (float)($validated['gastos_generales'] ?? $estimacion->gastos_generales) +
                           (float)($validated['contingencia'] ?? $estimacion->contingencia);
                
                $validated['subtotal'] = $subtotal;
            }

            $estimacion->update($validated);

            return response()->json(['success' => true, 'data' => $estimacion->load('proyecto')]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validación fallida', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(EstimacionCosto $estimacion)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        if ($estimacion->proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $estimacion->delete();
            return response()->json(['success' => true, 'message' => 'Estimación eliminada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function porProyecto($proyectoId)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        try {
            $proyecto = Proyecto::findOrFail($proyectoId);
            if ($proyecto->usuario_id !== $user->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $estimaciones = EstimacionCosto::where('proyecto_id', $proyectoId)
                ->orderBy('creado_en', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $estimaciones]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resumenFinanciero($proyectoId)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        try {
            $proyecto = Proyecto::findOrFail($proyectoId);
            if ($proyecto->usuario_id !== $user->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $estimaciones = EstimacionCosto::where('proyecto_id', $proyectoId)->get();

            $totalCostos = 0;
            $totalAhorros = 0;

            foreach ($estimaciones as $est) {
                $totalCostos += ($est->subtotal + $est->impuestos);
                $totalAhorros += ($est->ahorros_estimados ?? 0);
            }

            $optimizadas = $estimaciones->where('optimizacion_aplicada', true)->count();

            return response()->json([
                'success' => true,
                'total_costos' => round($totalCostos, 2),
                'total_ahorros' => round($totalAhorros, 2),
                'estimaciones_optimizadas' => $optimizadas,
                'total_estimaciones' => $estimaciones->count(),
                'potencial_ahorro_porcentaje' => $totalCostos > 0 ? round(($totalAhorros / $totalCostos) * 100, 2) : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
