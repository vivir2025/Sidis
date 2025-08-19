<?php
// app/Http/Controllers/Api/CupsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cups;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cups::query();

        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('origen')) {
            $query->porOrigen($request->origen);
        }

        if ($request->filled('codigo')) {
            $query->porCodigo($request->codigo);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->buscar($search);
        }

        // Solo activos por defecto
        if (!$request->filled('incluir_inactivos')) {
            $query->activos();
        }

        // Incluir conteos si se solicita
        if ($request->filled('with_counts')) {
            $query->withCount(['cupsContratados', 'citas']);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'codigo');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $cups = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cups,
            'message' => 'CUPS obtenidos exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origen' => 'required|string|max:10',
            'nombre' => 'required|string|max:200',
            'codigo' => 'required|string|max:10|unique:cups,codigo',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $cups = Cups::create($validated);

        return response()->json([
            'success' => true,
            'data' => $cups,
            'message' => 'CUPS creado exitosamente'
        ], 201);
    }

    public function show(Cups $cup): JsonResponse
    {
        $cup->loadCount(['cupsContratados', 'citas']);

        return response()->json([
            'success' => true,
            'data' => $cup,
            'message' => 'CUPS obtenido exitosamente'
        ]);
    }

    public function update(Request $request, Cups $cup): JsonResponse
    {
        $validated = $request->validate([
            'origen' => 'sometimes|string|max:10',
            'nombre' => 'sometimes|string|max:200',
            'codigo' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('cups', 'codigo')->ignore($cup->id)
            ],
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $cup->update($validated);

        return response()->json([
            'success' => true,
            'data' => $cup,
            'message' => 'CUPS actualizado exitosamente'
        ]);
    }

    public function destroy(Cups $cup): JsonResponse
    {
        // Verificar si tiene contratos asociados
        if ($cup->cupsContratados()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el CUPS porque tiene contratos asociados'
            ], 422);
        }

        $cup->delete();

        return response()->json([
            'success' => true,
            'message' => 'CUPS eliminado exitosamente'
        ]);
    }

    public function activos(): JsonResponse
    {
        $cups = Cups::activos()
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cups,
            'message' => 'CUPS activos obtenidos exitosamente'
        ]);
    }

    public function porOrigen(string $origen): JsonResponse
    {
        $cups = Cups::porOrigen($origen)
            ->activos()
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cups,
            'message' => "CUPS de origen {$origen} obtenidos exitosamente"
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $cups = Cups::buscar($request->q)
            ->activos()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cups,
            'message' => 'Búsqueda de CUPS completada'
        ]);
    }
}
