<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\AuthenticatesWithJWT;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class ProyectoController extends Controller
{
    use AuthenticatesWithJWT;

    /**
     * Obtener todos los proyectos (con filtros y paginación)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $query = Proyecto::query();

        // Filtrar por usuario (si no es admin, solo ve los suyos)
        if ($user->rol_id !== 1) { // Asumiendo 1 es Admin
            $query->where('usuario_id', $user->id);
        }

        // Búsqueda
        if ($request->has('search')) {
            $query->buscar($request->search);
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado !== 'todos') {
            $query->porEstado($request->estado);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'creado_en');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $proyectos = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $proyectos,
        ]);
    }

    /**
     * Crear nuevo proyecto
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_proyecto' => 'nullable|string',
            'ubicacion' => 'nullable|string|max:500',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'area_total' => 'nullable|numeric',
            'numero_pisos' => 'nullable|integer',
            'carga_maxima' => 'nullable|numeric',
        ]);

        try {
            $proyecto = Proyecto::create(array_merge($validated, [
                'usuario_id' => $user->id,
                'creado_por' => $user->id,
                'actualizado_por' => $user->id,
                'estado' => 'borrador',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Proyecto creado exitosamente',
                'data' => $proyecto,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando proyecto: ' . $e->getMessage());
            return response()->json(['error' => 'Error al crear el proyecto'], 500);
        }
    }

    /**
     * Obtener un proyecto específico con relaciones
     */
    public function show($id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::with([
            'analisisSuelo', 
            'cimentaciones', 
            'disenosEstructurales', 
            'estimacionesCosto'
        ])->find($id);

        if (!$proyecto) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        // Verificar permisos
        if ($user->rol_id !== 1 && $proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $proyecto,
        ]);
    }

    /**
     * Actualizar proyecto
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::find($id);
        if (!$proyecto) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        if ($user->rol_id !== 1 && $proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_proyecto' => 'nullable|string',
            'ubicacion' => 'nullable|string|max:500',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'area_total' => 'nullable|numeric',
            'numero_pisos' => 'nullable|integer',
            'carga_maxima' => 'nullable|numeric',
            'estado' => 'nullable|in:borrador,en_proceso,completado,archivado',
        ]);

        try {
            $validated['actualizado_por'] = $user->id;
            $proyecto->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Proyecto actualizado exitosamente',
                'data' => $proyecto,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar proyecto
     */
    public function destroy($id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::find($id);
        if (!$proyecto) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        if ($user->rol_id !== 1 && $proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $proyecto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Proyecto eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generar PDF del proyecto
     */
    public function generatePdf(Request $request, $id)
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $proyecto = Proyecto::with([
            'analisisSuelo', 
            'cimentaciones', 
            'disenosEstructurales', 
            'estimacionesCosto',
            'usuario'
        ])->find($id);

        if (!$proyecto) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        if ($user->rol_id !== 1 && $proyecto->usuario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $data = [
            'proyecto' => $proyecto,
            'generado_por' => $user->nombre,
            'fecha_generacion' => now()->format('d/m/Y H:i'),
            'include_details' => $request->query('details', 'yes') === 'yes',
            'include_materials' => $request->query('materials', 'yes') === 'yes',
        ];

        $pdf = Pdf::loadView('reports.proyecto', $data);
        
        return $pdf->download('proyecto-' . $proyecto->id . '.pdf');
    }
}