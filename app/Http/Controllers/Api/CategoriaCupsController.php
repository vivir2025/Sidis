<?php
// app/Http/Controllers/Api/CategoriaCupsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaCups;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CategoriaCupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CategoriaCups::query();

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->buscar($search);
        }

        // Incluir relaciones si se solicita
        if ($request->filled('with_cups')) {
            $query->withCount('cups');
        }

        if ($request->filled('with_contratados')) {
            $query->withCount('cupsContratados');
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'nombre');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $categorias = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categorias,
            'message' => 'Categorías CUPS obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50|unique:categorias_cups,nombre'
        ]);

        $categoria = CategoriaCups::create($validated);

        return response()->json([
            'success' => true,
            'data' => $categoria,
            'message' => 'Categoría CUPS creada exitosamente'
        ], 201);
    }

    public function show(CategoriaCups $categoriaCup): JsonResponse
    {
        $categoriaCup->loadCount(['cups', 'cupsContratados']);

        return response()->json([
            'success' => true,
            'data' => $categoriaCup,
            'message' => 'Categoría CUPS obtenida exitosamente'
        ]);
    }

    public function update(Request $request, CategoriaCups $categoriaCup): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('categorias_cups', 'nombre')->ignore($categoriaCup->id)
            ]
        ]);

        $categoriaCup->update($validated);

        return response()->json([
            'success' => true,
            'data' => $categoriaCup,
            'message' => 'Categoría CUPS actualizada exitosamente'
        ]);
    }

    public function destroy(CategoriaCups $categoriaCup): JsonResponse
    {
        // Verificar si tiene CUPS asociados
        if ($categoriaCup->cups()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la categoría porque tiene CUPS asociados'
            ], 422);
        }

        // Verificar si tiene contratos asociados
        if ($categoriaCup->cupsContratados()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la categoría porque tiene contratos asociados'
            ], 422);
        }

        $categoriaCup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría CUPS eliminada exitosamente'
        ]);
    }

    public function conCups(): JsonResponse
    {
        $categorias = CategoriaCups::with(['cups' => function ($query) {
            $query->activos();
        }])
        ->has('cups')
        ->orderBy('nombre')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $categorias,
            'message' => 'Categorías con CUPS obtenidas exitosamente'
        ]);
    }
}
