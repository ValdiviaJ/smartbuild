<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuariosController extends Controller
{
    /**
     * Obtener usuario autenticado
     */
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
     * Listar todos los usuarios (Solo ADMIN)
     * GET /api/usuarios
     */
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        
        // Verificar que sea admin
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $usuarios = Usuario::with('rol')
                ->orderBy('creado_en', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $usuarios,
                'count' => $usuarios->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener usuarios: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Crear nuevo usuario (Solo ADMIN)
     * POST /api/usuarios
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Verificar que sea admin
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'contraseña' => 'required|string|min:6',
            'confirmar_contraseña' => 'required|same:contraseña',
            'rol_id' => 'required|integer|exists:roles,id',
            'empresa' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'activo' => 'boolean',
        ]);

        try {
            $usuario = Usuario::create([
                'nombre' => $validated['nombre'],
                'correo' => $validated['correo'],
                'contraseña' => bcrypt($validated['contraseña']),
                'rol_id' => $validated['rol_id'],
                'empresa' => $validated['empresa'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'activo' => $validated['activo'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $usuario->load('rol'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un usuario específico (Solo ADMIN)
     * GET /api/usuarios/{id}
     */
    public function show($id): JsonResponse
    {
        $user = $this->getUser();
        
        // Verificar que sea admin
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $usuario = Usuario::with('rol')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $usuario,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
    }

    /**
     * Actualizar usuario (Solo ADMIN)
     * PUT /api/usuarios/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->getUser();
        
        // Verificar que sea admin
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $usuario = Usuario::findOrFail($id);

            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'correo' => 'sometimes|email|unique:usuarios,correo,' . $id,
                'rol_id' => 'sometimes|integer|exists:roles,id',
                'empresa' => 'nullable|string|max:255',
                'telefono' => 'nullable|string|max:20',
                'activo' => 'boolean',
            ]);

            $usuario->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => $usuario->load('rol'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar usuario (Solo ADMIN)
     * DELETE /api/usuarios/{id}
     */
    public function destroy($id): JsonResponse
    {
        $user = $this->getUser();
        
        // Verificar que sea admin
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $usuario = Usuario::findOrFail($id);
            
            // No eliminar si es el único admin
            if ($usuario->rol_id === 1) {
                $adminCount = Usuario::where('rol_id', 1)->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'No puedes eliminar el único administrador'], 400);
                }
            }

            $usuario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cambiar estado de usuario
     * PATCH /api/usuarios/{id}/toggle-estado
     */
    public function toggleEstado($id): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $usuario = Usuario::findOrFail($id);
            $usuario->activo = !$usuario->activo;
            $usuario->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado',
                'data' => $usuario,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cambiar estado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener estadísticas de usuarios
     * GET /api/usuarios/estadisticas/dashboard
     */
    public function estadisticas(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user || $user->rol_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $totalUsuarios = Usuario::count();
            $usuariosActivos = Usuario::where('activo', true)->count();
            $usuariosInactivos = Usuario::where('activo', false)->count();
            
            $usuariosPorRol = Usuario::selectRaw('rol_id, COUNT(*) as count')
                ->with('rol')
                ->groupBy('rol_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'rol' => $item->rol->nombre ?? 'Sin rol',
                        'count' => $item->count,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'totalUsuarios' => $totalUsuarios,
                    'usuariosActivos' => $usuariosActivos,
                    'usuariosInactivos' => $usuariosInactivos,
                    'usuariosPorRol' => $usuariosPorRol,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
        }
    }
}
