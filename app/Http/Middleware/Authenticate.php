<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * Para API REST siempre devuelve null (no redirige, devuelve error 401)
     */
    protected function redirectTo(Request $request): ?string
    {
        // API REST: siempre devolver null para que Laravel devuelva error 401 JSON
        return null;
    }
}
