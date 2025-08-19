<?php
// app/Http/Middleware/ApiResponseMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class ApiResponseMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo procesar respuestas JSON
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);

        // Si ya tiene el formato estándar, no modificar
        if (isset($data['success'])) {
            return $response;
        }

        // Formatear respuesta estándar
        $formattedData = [
            'success' => $response->isSuccessful(),
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'status_code' => $response->getStatusCode()
        ];

        if (!$response->isSuccessful()) {
            $formattedData['message'] = $data['message'] ?? 'Error en la solicitud';
            unset($formattedData['data']);
        }

        $response->setData($formattedData);

        return $response;
    }
}
