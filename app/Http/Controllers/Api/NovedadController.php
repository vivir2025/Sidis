<?php
// app/Http/Controllers/Api/NovedadController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Novedad;
use Illuminate\Http\JsonResponse;

class NovedadController extends Controller
{
    public function index(): JsonResponse
    {
        $novedades = Novedad::orderBy('tipo_novedad')->get();
        
        return response()->json([
            'success' => true,
            'data' => $novedades->map(function ($novedad) {
                return [
                    'uuid' => $novedad->uuid,
                    'tipo_novedad' => $novedad->tipo_novedad
                ];
            })
        ]);
    }
}
