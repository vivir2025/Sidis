<?php
// app/Http/Middleware/SedeAccessMiddleware.php - MODIFICADO
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

            // ✅ CAMBIO: Verificar sede de la sesión, no del usuario
            $sedeId = session('sede_id') ?? $usuario->sede_id;
            
            if (!$sedeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay sede seleccionada'
                ], 403);
            }

            // Cargar relaciones
            $usuario->load('sede', 'estado');

            // ✅ VERIFICAR LA SEDE SELECCIONADA EN LA SESIÓN
            $sede = \App\Models\Sede::find($sedeId);
            
            if (!$sede) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sede no encontrada'
                ], 403);
            }

            if (!$sede->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sede inactiva'
                ], 403);
            }

            // Verificar usuario activo
            if (!$usuario->estaActivo()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario inactivo'
                ], 403);
            }

            // ✅ AGREGAR SEDE ACTUAL AL REQUEST
            $request->merge(['sede_actual' => $sede]);

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
