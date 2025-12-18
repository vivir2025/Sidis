<?php
// app/Http/Controllers/Api/MedicamentoController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MedicamentoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Medicamento::query();
            
            // Filtro de búsqueda
            if ($request->filled('q')) {
                $query->buscar($request->q);
            }
            
            // ✅ REMOVER FILTRO DE ACTIVOS (no existe la columna)
            // Solo usar soft deletes si existe
            $query->whereNull('deleted_at');
            
            // ✅ NUEVO: Verificar si se solicitan TODOS los registros
            if ($request->get('all') === 'true' || $request->get('all') === true || $request->boolean('all')) {
                // Devolver todos los registros sin paginación
                $medicamentos = $query->orderBy('nombre')->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $medicamentos,
                    'message' => 'Medicamentos obtenidos exitosamente',
                    'total' => $medicamentos->count()
                ]);
            }
            
            $medicamentos = $query->orderBy('nombre')
                ->paginate($request->get('per_page', 50));
            
            return response()->json([
                'success' => true,
                'data' => $medicamentos->items(),
                'meta' => [
                    'current_page' => $medicamentos->currentPage(),
                    'last_page' => $medicamentos->lastPage(),
                    'per_page' => $medicamentos->perPage(),
                    'total' => $medicamentos->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo medicamentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function buscar(Request $request): JsonResponse
    {
        try {
            $termino = $request->get('q', '');
            
            $medicamentos = Medicamento::whereNull('deleted_at')
                ->buscar($termino)
                ->orderBy('nombre')
                ->limit(20)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $medicamentos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error buscando medicamentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
