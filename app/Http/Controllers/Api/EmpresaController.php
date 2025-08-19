<?php
// app/Http/Controllers/Api/EmpresaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmpresaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Empresa::with(['contratos']);

        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('codigo_eapb', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'nombre');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $empresas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $empresas,
            'message' => 'Empresas obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
            'nit' => 'required|string|max:50|unique:empresas,nit',
            'codigo_eapb' => 'required|string|max:50',
            'codigo' => 'required|string|max:50',
            'direccion' => 'required|string|max:50',
            'telefono' => 'required|string|max:10',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $empresa = Empresa::create($validated);

        return response()->json([
            'success' => true,
            'data' => $empresa,
            'message' => 'Empresa creada exitosamente'
        ], 201);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        $empresa->load(['contratos.facturas']);

        return response()->json([
            'success' => true,
            'data' => $empresa,
            'message' => 'Empresa obtenida exitosamente'
        ]);
    }

    public function update(Request $request, Empresa $empresa): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:50',
            'nit' => 'sometimes|string|max:50|unique:empresas,nit,' . $empresa->id,
            'codigo_eapb' => 'sometimes|string|max:50',
            'codigo' => 'sometimes|string|max:50',
            'direccion' => 'sometimes|string|max:50',
            'telefono' => 'sometimes|string|max:10',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $empresa->update($validated);

        return response()->json([
            'success' => true,
            'data' => $empresa,
            'message' => 'Empresa actualizada exitosamente'
        ]);
    }

    public function destroy(Empresa $empresa): JsonResponse
    {
        // Verificar si tiene contratos asociados
        if ($empresa->contratos()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la empresa porque tiene contratos asociados'
            ], 422);
        }

        $empresa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Empresa eliminada exitosamente'
        ]);
    }

    public function activas(): JsonResponse
    {
        $empresas = Empresa::activas()->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $empresas,
            'message' => 'Empresas activas obtenidas exitosamente'
        ]);
    }

    public function estadisticas(): JsonResponse
    {
        $estadisticas = [
            'total_empresas' => Empresa::count(),
            'empresas_activas' => Empresa::activas()->count(),
            'empresas_inactivas' => Empresa::inactivas()->count(),
            'total_contratos' => DB::table('contratos')->count(),
            'contratos_activos' => DB::table('contratos')->where('estado', 'ACTIVO')->count(),
            'empresas_con_mas_contratos' => Empresa::withCount('contratos')
                ->orderBy('contratos_count', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
