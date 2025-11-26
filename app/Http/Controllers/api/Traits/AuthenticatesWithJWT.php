<?php

namespace App\Http\Controllers\Api\Traits;

use App\Models\Usuario;
use Tymon\JWTAuth\Facades\JWTAuth;

trait AuthenticatesWithJWT
{
    /**
     * Obtener usuario autenticado desde JWT
     */
    protected function getAuthenticatedUser()
    {
        try {
            // Parse the token and get the payload
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            
            // Get user ID from payload
            $userId = $payload->get('sub');
            
            // Manually fetch the user
            $user = Usuario::find($userId);
            
            return $user;
        } catch (\Exception $e) {
            \Log::error('JWT Auth Failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e)
            ]);
            return null;
        }
    }
}
