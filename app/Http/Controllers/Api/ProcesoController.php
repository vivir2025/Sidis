<?php
// app/Http/Controllers/Api/ProcesoController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use Illuminate\Http\JsonResponse;

class ProcesoController extends Controller
{
    public function index(): JsonResponse
    {
        $procesos = Proceso::orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $procesos->map(function ($proceso) {
                return [
                    'uuid' => $proceso->uuid,
                    'nombre' => $proceso->nombre,
                    'n_cups' => $proceso->n_cups
                ];
            })
        ]);
    }
}
