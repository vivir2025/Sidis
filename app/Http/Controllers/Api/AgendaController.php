<?php
// app/Http/Controllers/Api/AgendaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AgendaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Agenda::with(['sede', 'proceso', 'usuario', 'brigada']);

        // Filtros
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('modalidad')) {
            $query->where('modalidad', $request->modalidad);
        }

        if ($request->filled('proceso_id')) {
            $query->where('proceso_id', $request->proceso_id);
        }

        if ($request->filled('brigada_id')) {
            $query->where('brigada_id', $request->brigada_id);
        }

        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('consultorio', 'like', "%{$search}%")
                  ->orWhere('etiqueta', 'like', "%{$search}%")
                  ->orWhereHas('proceso', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'fecha');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $agendas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agendas,
            'message' => 'Agendas obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'modalidad' => 'required|in:Telemedicina,Ambulatoria',
            'fecha' => 'required|date|after_or_equal:today',
            'consultorio' => 'required|string|max:50',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'intervalo' => 'required|string|max:10',
            'etiqueta' => 'required|string|max:50',
            'proceso_id' => 'required|exists:procesos,id',
            'usuario_id' => 'required|exists:usuarios,id',
            'brigada_id' => 'required|exists:brigadas,id',
        ]);

        // Validar que no exista conflicto de horarios
        $conflicto = Agenda::where('sede_id', $validated['sede_id'])
            ->where('consultorio', $validated['consultorio'])
            ->where('fecha', $validated['fecha'])
            ->where('estado', 'ACTIVO')
            ->where(function ($query) use ($validated) {
                $query->whereBetween('hora_inicio', [$validated['hora_inicio'], $validated['hora_fin']])
                      ->orWhereBetween('hora_fin', [$validated['hora_inicio'], $validated['hora_fin']])
                      ->orWhere(function ($q) use ($validated) {
                          $q->where('hora_inicio', '<=', $validated['hora_inicio'])
                            ->where('hora_fin', '>=', $validated['hora_fin']);
                      });
            })
            ->exists();

        if ($conflicto) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una agenda activa en ese horario y consultorio'
            ], 422);
        }

        $agenda = Agenda::create($validated);
        $agenda->load(['sede', 'proceso', 'usuario', 'brigada']);

        return response()->json([
            'success' => true,
            'data' => $agenda,
            'message' => 'Agenda creada exitosamente'
        ], 201);
    }

    public function show(Agenda $agenda): JsonResponse
    {
        $agenda->load(['sede', 'proceso', 'usuario', 'brigada', 'citas.paciente']);

        return response()->json([
            'success' => true,
            'data' => $agenda,
            'message' => 'Agenda obtenida exitosamente'
        ]);
    }

    public function update(Request $request, Agenda $agenda): JsonResponse
    {
        $validated = $request->validate([
            'modalidad' => 'sometimes|in:Telemedicina,Ambulatoria',
            'fecha' => 'sometimes|date|after_or_equal:today',
            'consultorio' => 'sometimes|string|max:50',
            'hora_inicio' => 'sometimes|date_format:H:i',
            'hora_fin' => 'sometimes|date_format:H:i|after:hora_inicio',
            'intervalo' => 'sometimes|string|max:10',
            'etiqueta' => 'sometimes|string|max:50',
            'estado' => ['sometimes', Rule::in(['ACTIVO', 'ANULADA', 'LLENA'])],
            'proceso_id' => 'sometimes|exists:procesos,id',
            'usuario_id' => 'sometimes|exists:usuarios,id',
            'brigada_id' => 'sometimes|exists:brigadas,id',
        ]);

        // Si se actualiza fecha u horario, validar conflictos
        if (isset($validated['fecha']) || isset($validated['hora_inicio']) || isset($validated['hora_fin'])) {
            $fecha = $validated['fecha'] ?? $agenda->fecha;
            $horaInicio = $validated['hora_inicio'] ?? $agenda->hora_inicio->format('H:i');
            $horaFin = $validated['hora_fin'] ?? $agenda->hora_fin->format('H:i');

            $conflicto = Agenda::where('sede_id', $agenda->sede_id)
                ->where('consultorio', $agenda->consultorio)
                ->where('fecha', $fecha)
                ->where('estado', 'ACTIVO')
                ->where('id', '!=', $agenda->id)
                ->where(function ($query) use ($horaInicio, $horaFin) {
                    $query->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                          ->orWhereBetween('hora_fin', [$horaInicio, $horaFin])
                          ->orWhere(function ($q) use ($horaInicio, $horaFin) {
                              $q->where('hora_inicio', '<=', $horaInicio)
                                ->where('hora_fin', '>=', $horaFin);
                          });
                })
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una agenda activa en ese horario y consultorio'
                ], 422);
            }
        }

        $agenda->update($validated);
        $agenda->load(['sede', 'proceso', 'usuario', 'brigada']);

        return response()->json([
            'success' => true,
            'data' => $agenda,
            'message' => 'Agenda actualizada exitosamente'
        ]);
    }

    public function destroy(Agenda $agenda): JsonResponse
    {
        // Verificar si tiene citas activas
        $citasActivas = $agenda->citas()->whereIn('estado', ['PROGRAMADA', 'EN_ATENCION'])->count();
        
        if ($citasActivas > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una agenda con citas activas'
            ], 422);
        }

        $agenda->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agenda eliminada exitosamente'
        ]);
    }

    public function disponibles(Request $request): JsonResponse
    {
        $query = Agenda::disponibles()
            ->with(['sede', 'proceso', 'usuario', 'brigada']);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('modalidad')) {
            $query->where('modalidad', $request->modalidad);
        }

        if ($request->filled('proceso_id')) {
            $query->where('proceso_id', $request->proceso_id);
        }

        $agendas = $query->orderBy('fecha', 'asc')
                        ->orderBy('hora_inicio', 'asc')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $agendas,
            'message' => 'Agendas disponibles obtenidas exitosamente'
        ]);
    }

    public function citasAgenda(Agenda $agenda): JsonResponse
    {
        $citas = $agenda->citas()
            ->with(['paciente', 'usuario'])
            ->orderBy('hora_cita', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $citas,
            'message' => 'Citas de la agenda obtenidas exitosamente'
        ]);
    }
}
