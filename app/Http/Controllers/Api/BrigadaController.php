<?php
// app/Http/Controllers/Api/BrigadaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brigada;
use Illuminate\Http\JsonResponse;

class BrigadaController extends Controller
{
    public function index(): JsonResponse
    {
        $brigadas = Brigada::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $brigadas->map(function ($brigada) {
                return [
                    'uuid' => $brigada->uuid,
                    'nombre' => $brigada->nombre
                ];
            })
        ]);
    }
}
