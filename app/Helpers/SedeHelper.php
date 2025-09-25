<?php
// app/Helpers/SedeHelper.php
namespace App\Helpers;

class SedeHelper
{
    /**
     * Obtener información de la sede actual
     */
    public static function sedeActual(): array
    {
        $authService = app(\App\Services\AuthService::class);
        return $authService->sedeActual() ?? [
            'id' => null,
            'nombre' => 'Sin sede'
        ];
    }

    /**
     * Verificar si el usuario puede acceder a una sede específica
     */
    public static function puedeAccederASede(int $sedeId): bool
    {
        // Con el nuevo sistema, todos los usuarios pueden acceder a cualquier sede
        return true;
    }

    /**
     * Obtener el ID de la sede actual
     */
    public static function sedeId(): ?int
    {
        $authService = app(\App\Services\AuthService::class);
        return $authService->sedeId();
    }

    /**
     * Verificar si está en modo multi-sede
     */
    public static function esMultiSede(): bool
    {
        $authService = app(\App\Services\AuthService::class);
        $usuario = $authService->usuario();
        
        if (!$usuario) return false;
        
        // Verificar si la sede actual es diferente a la sede original del usuario
        $sedeOriginal = $usuario['sede_original_id'] ?? $usuario['sede_id'];
        $sedeActual = $authService->sedeId();
        
        return $sedeOriginal !== $sedeActual;
    }
}
