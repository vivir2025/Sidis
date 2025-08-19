<?php
// app/Http/Controllers/Api/EspecialidadController.php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class EspecialidadController extends Controller
{
    /**
     * Mostrar lista de especialidades
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Especialidad::with(['estado']);

            // Filtros
            if ($request->filled('activas')) {
                $query->activas();
            }

            if ($request->filled('buscar')) {
                $query->buscar($request->buscar);
            }

            if ($request->filled('con_medicos')) {
                $query->conMedicos();
            }

            // Paginación o todos los registros
            if ($request->filled('per_page')) {
                $especialidades = $query->ordenadoPorNombre()
                    ->paginate($request->per_page);
            } else {
                $especialidades = $query->ordenadoPorNombre()->get();
            }

            return response()->json([
                'success' => true,
                'data' => $especialidades,
                'message' => 'Especialidades obtenidas correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva especialidad
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:100|unique:especialidades,nombre',
                'descripcion' => 'nullable|string|max:500',
                'codigo' => 'nullable|string|max:10|unique:especialidades,codigo',
                'estado_id' => 'required|exists:estados,id',
                'duracion_cita_minutos' => 'nullable|integer|min:15|max:240'
            ]);

            $especialidad = Especialidad::create($validated);
            $especialidad->load('estado');

            return response()->json([
                'success' => true,
                'data' => $especialidad,
                'message' => 'Especialidad creada correctamente'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear especialidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar especialidad específica
     */
    public function show(string $id): JsonResponse
    {
        try {
            $especialidad = Especialidad::with(['estado', 'medicos.estado'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $especialidad,
                'message' => 'Especialidad obtenida correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Especialidad no encontrada'
            ], 404);
        }
    }

    /**
     * Actualizar especialidad
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $especialidad = Especialidad::findOrFail($id);

            $validated = $request->validate([
                'nombre' => 'required|string|max:100|unique:especialidades,nombre,' . $id,
                'descripcion' => 'nullable|string|max:500',
                'codigo' => 'nullable|string|max:10|unique:especialidades,codigo,' . $id,
                'estado_id' => 'required|exists:estados,id',
                'duracion_cita_minutos' => 'nullable|integer|min:15|max:240'
            ]);

            $especialidad->update($validated);
            $especialidad->load('estado');

            return response()->json([
                'success' => true,
                'data' => $especialidad,
                'message' => 'Especialidad actualizada correctamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar especialidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar especialidad (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $especialidad = Especialidad::findOrFail($id);

            // Verificar si tiene médicos asignados
            if ($especialidad->tieneMedicos()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la especialidad porque tiene médicos asignados'
                ], 422);
            }

            $especialidad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Especialidad eliminada correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar especialidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaurar especialidad eliminada
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $especialidad = Especialidad::withTrashed()->findOrFail($id);
            $especialidad->restore();

            return response()->json([
                'success' => true,
                'data' => $especialidad,
                'message' => 'Especialidad restaurada correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar especialidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener médicos de una especialidad
     */
    public function medicos(string $id): JsonResponse
    {
        try {
            $especialidad = Especialidad::with(['medicos.estado', 'medicos.sede'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'especialidad' => $especialidad->only(['id', 'nombre', 'descripcion']),
                    'medicos' => $especialidad->medicos
                ],
                'message' => 'Médicos de la especialidad obtenidos correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener médicos de la especialidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado de especialidad
     */
    public function cambiarEstado(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'estado_id' => 'required|exists:estados,id'
            ]);

            $especialidad = Especialidad::findOrFail($id);
            $especialidad->update(['estado_id' => $validated['estado_id']]);
            $especialidad->load('estado');

            return response()->json([
                'success' => true,
                'data' => $especialidad,
                'message' => 'Estado de especialidad actualizado correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener especialidades activas para selects
     */
    public function activas(): JsonResponse
    {
        try {
            $especialidades = Especialidad::activas()
                ->ordenadoPorNombre()
                ->get(['id', 'nombre', 'descripcion', 'duracion_cita_minutos']);

            return response()->json([
                'success' => true,
                'data' => $especialidades,
                'message' => 'Especialidades activas obtenidas correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades activas: ' . $e->getMessage()
            ], 500);
        }
    }
}
