<?php
namespace App\Http\Controllers\Api;
// app/Http/Controllers/Api/RemisionController.php

use App\Http\Controllers\Controller;
use App\Models\Remision;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RemisionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Remision::with('especialidad');
            
            // Filtro de búsqueda
            if ($request->filled('q')) {
                $query->where('nombre', 'like', "%{$request->q}%")
                      ->orWhere('codigo', 'like', "%{$request->q}%");
            }
            
            // Filtro por tipo
            if ($request->filled('tipo')) {
                $query->porTipo($request->tipo);
            }
            
            // Solo activas por defecto
            if (!$request->filled('incluir_inactivas')) {
                $query->activas();
            }
            
            $remisiones = $query->orderBy('nombre')
                ->paginate($request->get('per_page', 50));
            
            return response()->json([
                'success' => true,
                'data' => $remisiones->items(),
                'meta' => [
                    'current_page' => $remisiones->currentPage(),
                    'last_page' => $remisiones->lastPage(),
                    'per_page' => $remisiones->perPage(),
                    'total' => $remisiones->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo remisiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function buscar(Request $request): JsonResponse
    {
        try {
            $termino = $request->get('q', '');
            
            $remisiones = Remision::activas()
                ->with('especialidad')
                ->where(function($q) use ($termino) {
                    $q->where('nombre', 'like', "%{$termino}%")
                      ->orWhere('codigo', 'like', "%{$termino}%");
                })
                ->orderBy('nombre')
                ->limit(20)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $remisiones
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error buscando remisiones'
            ], 500);
        }
    }
}
