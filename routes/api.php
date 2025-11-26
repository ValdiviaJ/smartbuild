<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AnalisisSueloController;
use App\Http\Controllers\Api\ProyectoController;
use App\Http\Controllers\Api\CimentacionController;
use App\Http\Controllers\Api\DisenoEstructuralController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\EstimacionCostoController;
use App\Http\Controllers\Api\UsuariosController;
use App\Http\Controllers\Auth\LoginController;

// ============================================================
// RUTAS PÚBLICAS (SIN AUTENTICACIÓN)
// ============================================================

Route::post('/password/email', [App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [App\Http\Controllers\Auth\PasswordResetController::class, 'reset']);

Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [LoginController::class, 'register']);

// DEBUG - Temporal para ver qué está pasando
Route::get('/debug-auth', function (Request $request) {
    return response()->json([
        'headers' => $request->headers->all(),
        'token' => $request->bearerToken(),
        'authorization' => $request->header('Authorization'),
    ]);
});

// DEBUG - Ver qué pasa con JWT middleware
Route::middleware('jwt.auth')->get('/debug-jwt', function (Request $request) {
    try {
        $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
        return response()->json([
            'success' => true,
            'user' => $user,
            'user_class' => get_class($user),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ], 401);
    }
});



// ============================================================
// RUTAS PROTEGIDAS (CON JWT)
// ============================================================

Route::middleware('api')->group(function () {
    Route::get('/me', function (Request $request) {
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            
            if (!$user) {
                return response()->json(['error' => 'No autorizado'], 401);
            }
            
            return response()->json([
                'success' => true,
                'usuario' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido o expirado', 'message' => $e->getMessage()], 401);
        }
    });

    // Profile management routes
    Route::get('/user/profile', function (Request $request) {
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            
            if (!$user) {
                return response()->json(['error' => 'No autorizado'], 401);
            }
            
            return response()->json([
                'success' => true,
                'usuario' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }
    });

    Route::put('/user/profile', function (Request $request) {
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            
            if (!$user) {
                return response()->json(['error' => 'No autorizado'], 401);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'correo' => 'nullable|email|max:255|unique:usuarios,correo,' . $user->id,
                'telefono' => 'nullable|string|max:20',
                'empresa' => 'nullable|string|max:255',
                'licencia_profesional' => 'nullable|string|max:100',
            ]);

            $user->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'usuario' => $user->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el perfil'], 500);
        }
    });

    Route::put('/user/password', function (Request $request) {
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            
            if (!$user) {
                return response()->json(['error' => 'No autorizado'], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6',
                'new_password_confirmation' => 'required|string|same:new_password',
            ]);

            // Verify current password
            if (!\Hash::check($validated['current_password'], $user->contraseña)) {
                return response()->json(['error' => 'La contraseña actual es incorrecta'], 400);
            }

            // Update password
            $user->update(['contraseña' => \Hash::make($validated['new_password'])]);
            
            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cambiar la contraseña'], 500);
        }
    });

    Route::post('/logout', [LoginController::class, 'logout']);

    Route::apiResource('usuarios', UsuariosController::class);
    Route::patch('/usuarios/{id}/toggle-estado', [UsuariosController::class, 'toggleEstado']);
    Route::get('/usuarios/estadisticas/dashboard', [UsuariosController::class, 'estadisticas']);

    // ===== PROYECTOS =====
    Route::get('/proyectos/{id}/pdf', [ProyectoController::class, 'generatePdf']);
    Route::apiResource('proyectos', ProyectoController::class);
    Route::get('/proyectos/activos', [ProyectoController::class, 'activos']);
    Route::get('/proyectos/estado/{estado}', [ProyectoController::class, 'porEstado']);
    Route::post('/proyectos/{proyecto}/cambiar-estado', [ProyectoController::class, 'cambiarEstado']);

    // ===== ANÁLISIS DE SUELO =====
    Route::apiResource('analisis-suelo', AnalisisSueloController::class);
    Route::apiResource('calculos-rapidos', \App\Http\Controllers\Api\CalculoRapidoController::class);
    Route::post('/analisis-suelo/procesar-archivo', [AnalisisSueloController::class, 'procesarArchivo']);
    Route::get('/suelos/tipos', [AnalisisSueloController::class, 'obtenerTiposSuelo']);
    Route::get('/suelos/info/{tipoSuelo}', [AnalisisSueloController::class, 'obtenerInfoTipoSuelo']);
    Route::get('/proyectos/{proyecto}/analisis-suelo', [AnalisisSueloController::class, 'obtenerAnalisisSueloDisponibles']);

    // ===== CIMENTACIONES =====
    Route::apiResource('cimentaciones', CimentacionController::class);
    Route::get('/proyectos/{proyecto}/cimentaciones', [CimentacionController::class, 'obtenerCimentacionesDisponibles']);

    // ===== DISEÑO ESTRUCTURAL =====
    Route::get('/disenos-estructurales/{id}/analizar', [DisenoEstructuralController::class, 'analizar']);
    Route::apiResource('disenos-estructurales', DisenoEstructuralController::class);
    Route::get('/proyectos/{proyectoId}/cimentaciones-design', [DisenoEstructuralController::class, 'obtenerCimentacionesDisponibles']);

    // ===== MATERIALES =====
    Route::get('/materiales/stock/bajo', [MaterialController::class, 'stockBajo']);
    Route::get('/materiales-categorias', [MaterialController::class, 'categorias']);
    Route::get('/materiales-valor-inventario', [MaterialController::class, 'valorInventario']);
    Route::apiResource('materiales', MaterialController::class);

    Route::apiResource('estimaciones-costos', EstimacionCostoController::class);
    Route::get('/proyectos/{proyectoId}/estimaciones', [EstimacionCostoController::class, 'porProyecto']);
    Route::get('/proyectos/{proyectoId}/resumen-financiero', [EstimacionCostoController::class, 'resumenFinanciero']);
});
// ============================================================
// RUTA DE FALLBACK (404)
// ============================================================

Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint no encontrado',
        'status' => 404
    ], 404);
});