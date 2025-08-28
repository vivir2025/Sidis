<?php
// app/Http/Controllers/Api/AuxiliarController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auxiliar;
use Illuminate\Http\JsonResponse;

class AuxiliarController extends Controller
{
    public function index(): JsonResponse
    {
        $auxiliares = Auxiliar::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $auxiliares->map(function ($auxiliar) {
                return [
                    'uuid' => $auxiliar->uuid,
                    'nombre' => $auxiliar->nombre
                ];
            })
        ]);
    }
}
