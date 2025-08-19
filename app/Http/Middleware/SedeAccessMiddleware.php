<?php
// app/Http/Middleware/SedeAccessMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SedeAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        if (!$user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario sin sede asignada'
            ], 403);
        }

        // ✅ CAMBIAR 'activa' por 'activo'
        if (!$user->sede || !$user->sede->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Sede inactiva'
            ], 403);
        }

        // Verificar que el usuario esté activo
        if ($user->estado->nombre !== 'ACTIVO') {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo'
            ], 403);
        }

        return $next($request);
    }
}
