<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\ResetPasswordMail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Por seguridad, no revelamos si el usuario existe o no
            return response()->json(['message' => 'Si el correo existe, se ha enviado un enlace de recuperación.']);
        }

        $token = Str::random(60);

        // Guardar token en la tabla password_resets
        // Usamos updateOrInsert para manejar casos donde ya exista un token previo
        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        try {
            Mail::to($request->email)->send(new ResetPasswordMail($token, $request->email));
            return response()->json(['message' => 'Enlace de recuperación enviado. Revisa tu correo.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al enviar el correo: ' . $e->getMessage()], 500);
        }
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        // Verificar token en la tabla password_resets
        $resetRecord = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json(['error' => 'Token inválido o correo incorrecto.'], 400);
        }

        // Verificar expiración (ej. 60 minutos)
        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_resets')->where('email', $request->email)->delete();
            return response()->json(['error' => 'El enlace ha expirado. Solicita uno nuevo.'], 400);
        }

        // Actualizar contraseña del usuario
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Eliminar el token usado
        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Contraseña restablecida exitosamente. Ahora puedes iniciar sesión.']);
    }
}
