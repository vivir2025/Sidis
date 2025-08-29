<?php
// app/Http/Controllers/Api/ProcesoController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProcesoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Proceso::query();

            // Filtros opcionales
            if ($request->filled('buscar')) {
                $query->buscar($request->buscar);
            }

            if ($request->filled('con_cups')) {
                $query->conCups();
            }

            $procesos = $query->orderBy('nombre')->get();

            return response()->json([
                'success' => true,
                'data' => $procesos->map(function ($proceso) {
                    return [
                        'uuid' => $proceso->uuid,
                        'nombre' => $proceso->nombre,
                        'n_cups' => $proceso->n_cups,
                        'created_at' => $proceso->created_at,
                        'updated_at' => $proceso->updated_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo procesos', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo procesos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'n_cups' => 'nullable|string|max:20'
            ]);

            $proceso = Proceso::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $proceso->uuid,
                    'nombre' => $proceso->nombre,
                    'n_cups' => $proceso->n_cups
                ],
                'message' => 'Proceso creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando proceso', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error creando proceso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $proceso = Proceso::where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $proceso->uuid,
                    'nombre' => $proceso->nombre,
                    'n_cups' => $proceso->n_cups,
                    'created_at' => $proceso->created_at,
                    'updated_at' => $proceso->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Proceso no encontrado'
            ], 404);
        }
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $proceso = Proceso::where('uuid', $uuid)->firstOrFail();

            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'n_cups' => 'nullable|string|max:20'
            ]);

            $proceso->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $proceso->uuid,
                    'nombre' => $proceso->nombre,
                    'n_cups' => $proceso->n_cups
                ],
                'message' => 'Proceso actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error actualizando proceso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        try {
            $proceso = Proceso::where('uuid', $uuid)->firstOrFail();
            $proceso->delete();

            return response()->json([
                'success' => true,
                'message' => 'Proceso eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error eliminando proceso: ' . $e->getMessage()
            ], 500);
        }
    }
}
