<?php
// app/Http/Controllers/Api/RemisionController.php
namespace App\Http\Controllers\Api;

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
                $query->where('tipo', $request->tipo);
            }
            
            // ✅ REMOVER FILTRO DE ACTIVAS
            $query->whereNull('deleted_at');
            
            // ✅ NUEVO: Verificar si se solicitan TODOS los registros
            if ($request->get('all') === 'true' || $request->get('all') === true || $request->boolean('all')) {
                // Devolver todos los registros sin paginación
                $remisiones = $query->orderBy('nombre')->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $remisiones,
                    'message' => 'Remisiones obtenidas exitosamente',
                    'total' => $remisiones->count()
                ]);
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
            
            $remisiones = Remision::whereNull('deleted_at')
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
                'message' => 'Error buscando remisiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
