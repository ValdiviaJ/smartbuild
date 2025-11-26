<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HandleValidationException
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'El servidor rechazó los datos',
                'errors' => $e->validator->errors(),
            ], 422);
        }
    }
}