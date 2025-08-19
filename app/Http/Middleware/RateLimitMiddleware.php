<?php
// app/Http/Middleware/RateLimitMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);

        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }

        $this->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';
        return sha1($userId . '|' . $request->ip() . '|' . $request->path());
    }

    protected function resolveMaxAttempts(Request $request, int $maxAttempts): int
    {
        // Diferentes límites según el endpoint
        if ($request->is('*/sync/*')) {
            return 10; // Límite más bajo para sincronización
        }
        
        if ($request->is('*/auth/*')) {
            return 5; // Límite más bajo para autenticación
        }

        return $maxAttempts;
    }

    protected function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return Cache::get($key, 0) >= $maxAttempts;
    }

    protected function hit(string $key, int $decaySeconds): void
    {
        Cache::put($key, Cache::get($key, 0) + 1, $decaySeconds);
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - Cache::get($key, 0));
    }

    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = Cache::get($key . ':timer', 60);

        return response()->json([
            'success' => false,
            'message' => 'Demasiadas solicitudes. Intente nuevamente en ' . $retryAfter . ' segundos.',
            'retry_after' => $retryAfter
        ], 429)->header('Retry-After', $retryAfter);
    }

    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }
}
