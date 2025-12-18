<?php
// app/Http/Controllers/Api/DiagnosticoController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diagnostico;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiagnosticoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Diagnostico::query();
            
            // Filtro de búsqueda
            if ($request->filled('q')) {
                $query->buscar($request->q);
            }
            
            // ✅ REMOVER FILTRO DE ACTIVOS
            $query->whereNull('deleted_at');
            
            // ✅ NUEVO: Verificar si se solicitan TODOS los registros
            if ($request->get('all') === 'true' || $request->get('all') === true || $request->boolean('all')) {
                // Devolver todos los registros sin paginación
                $diagnosticos = $query->orderBy('codigo')->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $diagnosticos,
                    'message' => 'Diagnósticos obtenidos exitosamente',
                    'total' => $diagnosticos->count()
                ]);
            }
            
            $diagnosticos = $query->orderBy('codigo')
                ->paginate($request->get('per_page', 50));
            
            return response()->json([
                'success' => true,
                'data' => $diagnosticos->items(),
                'meta' => [
                    'current_page' => $diagnosticos->currentPage(),
                    'last_page' => $diagnosticos->lastPage(),
                    'per_page' => $diagnosticos->perPage(),
                    'total' => $diagnosticos->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo diagnósticos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function buscar(Request $request): JsonResponse
    {
        try {
            $termino = $request->get('q', '');
            
            $diagnosticos = Diagnostico::whereNull('deleted_at')
                ->buscar($termino)
                ->orderBy('codigo')
                ->limit(20)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $diagnosticos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error buscando diagnósticos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
