<?php
// app/Http/Controllers/Api/CupsContratadoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CupsContratado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CupsContratadoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CupsContratado::with(['contrato', 'categoriaCups', 'cups']);

        // Filtros
        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
        }

        if ($request->filled('categoria_cups_id')) {
            $query->where('categoria_cups_id', $request->categoria_cups_id);
        }

        if ($request->filled('cups_id')) {
            $query->where('cups_id', $request->cups_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Solo activos por defecto
        if (!$request->filled('incluir_inactivos')) {
            $query->activos();
        }

        // Solo contratos vigentes
        if ($request->filled('solo_vigentes')) {
            $query->whereHas('contrato', function ($q) {
                $q->vigentes()->activos();
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('cups', function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        // Incluir conteos si se solicita
        if ($request->filled('with_counts')) {
            $query->withCount('citas');
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'cups_codigo') {
            $query->join('cups', 'cups_contratados.cups_id', '=', 'cups.id')
                  ->orderBy('cups.codigo', $sortOrder)
                  ->select('cups_contratados.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $cupsContratados = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cupsContratados,
            'message' => 'CUPS contratados obtenidos exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'categoria_cups_id' => 'required|exists:categorias_cups,id',
            'cups_id' => 'required|exists:cups,id',
            'tarifa' => 'required|string|max:30',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        // Verificar que no exista la misma combinación
        $existe = CupsContratado::where('contrato_id', $validated['contrato_id'])
            ->where('cups_id', $validated['cups_id'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un CUPS contratado con esta combinación'
            ], 422);
        }

        $cupsContratado = CupsContratado::create($validated);
        $cupsContratado->load(['contrato', 'categoriaCups', 'cups']);

        return response()->json([
            'success' => true,
            'data' => $cupsContratado,
            'message' => 'CUPS contratado creado exitosamente'
        ], 201);
    }

    public function show(CupsContratado $cupsContratado): JsonResponse
    {
        $cupsContratado->load(['contrato', 'categoriaCups', 'cups']);
        $cupsContratado->loadCount('citas');

        return response()->json([
            'success' => true,
            'data' => $cupsContratado,
            'message' => 'CUPS contratado obtenido exitosamente'
        ]);
    }

    public function update(Request $request, CupsContratado $cupsContratado): JsonResponse
    {
        $validated = $request->validate([
            'contrato_id' => 'sometimes|exists:contratos,id',
            'categoria_cups_id' => 'sometimes|exists:categorias_cups,id',
            'cups_id' => 'sometimes|exists:cups,id',
            'tarifa' => 'sometimes|string|max:30',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        // Si se cambia contrato_id o cups_id, verificar unicidad
        if (isset($validated['contrato_id']) || isset($validated['cups_id'])) {
            $contratoId = $validated['contrato_id'] ?? $cupsContratado->contrato_id;
            $cupsId = $validated['cups_id'] ?? $cupsContratado->cups_id;

            $existe = CupsContratado::where('contrato_id', $contratoId)
                ->where('cups_id', $cupsId)
                ->where('id', '!=', $cupsContratado->id)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un CUPS contratado con esta combinación'
                ], 422);
            }
        }

        $cupsContratado->update($validated);
        $cupsContratado->load(['contrato', 'categoriaCups', 'cups']);

        return response()->json([
            'success' => true,
            'data' => $cupsContratado,
            'message' => 'CUPS contratado actualizado exitosamente'
        ]);
    }

    public function destroy(CupsContratado $cupsContratado): JsonResponse
    {
        // Verificar si tiene citas asociadas
        if ($cupsContratado->citas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el CUPS contratado porque tiene citas asociadas'
            ], 422);
        }

        $cupsContratado->delete();

        return response()->json([
            'success' => true,
            'message' => 'CUPS contratado eliminado exitosamente'
        ]);
    }

    public function porContrato(int $contratoId): JsonResponse
    {
        $cupsContratados = CupsContratado::with(['categoriaCups', 'cups'])
            ->where('contrato_id', $contratoId)
            ->activos()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cupsContratados,
            'message' => 'CUPS contratados por contrato obtenidos exitosamente'
        ]);
    }

    public function disponibles(Request $request): JsonResponse
    {
        $query = CupsContratado::with(['contrato', 'categoriaCups', 'cups'])
            ->activos()
            ->whereHas('contrato', function ($q) {
                $q->vigentes()->activos();
            });

        // Filtro por sede si se proporciona
        if ($request->filled('sede_id')) {
            // Aquí podrías agregar lógica para filtrar por sede
            // dependiendo de cómo esté estructurada tu base de datos
        }

        $cupsContratados = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $cupsContratados,
            'message' => 'CUPS contratados disponibles obtenidos exitosamente'
        ]);
    }

    public function activarDesactivar(CupsContratado $cupsContratado): JsonResponse
    {
        $nuevoEstado = $cupsContratado->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $cupsContratado->update(['estado' => $nuevoEstado]);

        return response()->json([
            'success' => true,
            'data' => $cupsContratado,
            'message' => "CUPS contratado {$nuevoEstado} exitosamente"
        ]);
    }

    public function masivos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'categoria_cups_id' => 'required|exists:categorias_cups,id',
            'cups_ids' => 'required|array',
            'cups_ids.*' => 'exists:cups,id',
            'tarifa' => 'required|string|max:30',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $creados = [];
        $errores = [];

        foreach ($validated['cups_ids'] as $cupsId) {
            try {
                // Verificar si ya existe
                $existe = CupsContratado::where('contrato_id', $validated['contrato_id'])
                    ->where('cups_id', $cupsId)
                    ->exists();

                if (!$existe) {
                    $cupsContratado = CupsContratado::create([
                        'contrato_id' => $validated['contrato_id'],
                        'categoria_cups_id' => $validated['categoria_cups_id'],
                        'cups_id' => $cupsId,
                        'tarifa' => $validated['tarifa'],
                        'estado' => $validated['estado'] ?? 'ACTIVO'
                    ]);
                    $creados[] = $cupsContratado;
                } else {
                    $errores[] = "CUPS ID {$cupsId} ya existe en el contrato";
                }
            } catch (\Exception $e) {
                $errores[] = "Error al crear CUPS ID {$cupsId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($creados) > 0,
            'data' => [
                'creados' => count($creados),
                'errores' => $errores,
                'cups_contratados' => $creados
            ],
            'message' => count($creados) > 0 
                ? "Se crearon " . count($creados) . " CUPS contratados exitosamente"
                : "No se pudo crear ningún CUPS contratado"
        ], count($creados) > 0 ? 201 : 422);
    }

    public function porCupsUuid(string $cupsUuid): JsonResponse
{
    try {
        $cupsContratado = CupsContratado::with(['contrato', 'categoriaCups', 'cups'])
            ->whereHas('cups', function ($q) use ($cupsUuid) {
                $q->where('uuid', $cupsUuid);
            })
            ->activos()
            ->whereHas('contrato', function ($q) {
                $q->vigentes()->activos();
            })
            ->first();

        if (!$cupsContratado) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un CUPS contratado vigente para este CUPS'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cupsContratado,
            'message' => 'CUPS contratado encontrado'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
