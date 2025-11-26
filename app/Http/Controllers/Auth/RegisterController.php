<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
    // Validaciones
    $validated = $request->validate([
        'nombre' => 'required|string|max:255',
        'correo' => 'required|string|email|max:255|unique:usuarios,correo',
        'contraseña' => 'required|string|min:6',
        'confirmar_contraseña' => 'required|string|min:6|same:contraseña',
        'rol_id' => 'required|integer|exists:roles,id',
        'telefono' => 'nullable|string|max:20',
        'empresa' => 'nullable|string|max:255',
        'licencia_profesional' => 'nullable|string|max:100',
    ], [
        'nombre.required' => 'El nombre es requerido',
        'correo.required' => 'El correo es requerido',
        'correo.email' => 'El correo debe ser válido',
        'correo.unique' => 'Este correo ya está registrado',
        'contraseña.required' => 'La contraseña es requerida',
        'contraseña.min' => 'La contraseña debe tener al menos 6 caracteres',
        'confirmar_contraseña.same' => 'Las contraseñas no coinciden',
        'rol_id.required' => 'El rol es requerido',
        'rol_id.exists' => 'El rol seleccionado no existe',
    ]);

    try {
        // Crear usuario
        $usuario = Usuario::create([
            'nombre' => $validated['nombre'],
            'correo' => $validated['correo'],
            'contraseña' => Hash::make($validated['contraseña']),
            'rol_id' => $validated['rol_id'],
            'telefono' => $validated['telefono'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'licencia_profesional' => $validated['licencia_profesional'] ?? null,
            'activo' => 1,
        ]);

        // Generar token JWT
        $token = JWTAuth::fromUser($usuario);

        // Cargar relación de rol
        $usuario->load('rol');

        return response()->json([
            'mensaje' => 'Usuario registrado correctamente',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'correo' => $usuario->correo,
                'rol_id' => $usuario->rol_id,
                'rol' => $usuario->rol,
                'telefono' => $usuario->telefono,
                'empresa' => $usuario->empresa,
            ],
            'token' => $token,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'mensaje' => 'Error al crear la cuenta',
            'error' => $e->getMessage(),
        ], 500);
    }
    }
}