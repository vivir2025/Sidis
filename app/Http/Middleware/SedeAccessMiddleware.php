<?php
// app/Http/Middleware/SedeAccessMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class SedeAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $usuario = auth()->user();
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            if (!$usuario->sede_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario sin sede asignada'
                ], 403);
            }

            // Cargar relaciones
            $usuario->load('sede', 'estado');

            // Verificar sede existe
            if (!$usuario->sede) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sede no encontrada'
                ], 403);
            }

            // ✅ CAMBIO PRINCIPAL: usar 'activa' en lugar de 'activo'
            if (!$usuario->sede->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sede inactiva'
                ], 403);
            }

            // Verificar usuario activo usando el método del modelo
            if (!$usuario->estaActivo()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario inactivo'
                ], 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error en SedeAccessMiddleware:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'usuario_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}
