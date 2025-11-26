<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = Usuario::where('correo', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'No encontramos un usuario con ese correo electrónico.'
            ], 404);
        }

        // Delete old tokens for this email
        DB::table('password_resets')->where('email', $request->email)->delete();

        // Generate new token
        $token = Str::random(60);

        // Store token in database
        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send email notification
        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'success' => true,
            'message' => 'Te hemos enviado un enlace para restablecer tu contraseña a tu correo electrónico.'
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        // Find the password reset record
        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'error' => 'Token de restablecimiento inválido.'
            ], 400);
        }

        // Check if token matches
        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'error' => 'Token de restablecimiento inválido.'
            ], 400);
        }

        // Check if token is expired (60 minutes)
        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            return response()->json([
                'error' => 'El token de restablecimiento ha expirado.'
            ], 400);
        }

        // Find user and update password
        $user = Usuario::where('correo', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Usuario no encontrado.'
            ], 404);
        }

        $user->update([
            'contraseña' => Hash::make($request->password)
        ]);

        // Delete the used token
        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña ha sido restablecida exitosamente.'
        ]);
    }
}
