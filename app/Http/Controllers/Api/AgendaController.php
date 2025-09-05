<?php
// app/Http/Controllers/Api/AgendaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
    try {
        Log::info('🔍 AgendaController@store - Datos RAW recibidos', [
            'all_data' => $request->all(),
            'proceso_id_raw' => $request->input('proceso_id'),
            'brigada_id_raw' => $request->input('brigada_id'),
            'usuario_medico_uuid_raw' => $request->input('usuario_medico_uuid') 
        ]);

        // ✅ VALIDACIÓN ACTUALIZADA - AGREGAR usuario_medico_id
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'modalidad' => 'required|in:Telemedicina,Ambulatoria',
            'fecha' => 'required|date|after_or_equal:today',
            'consultorio' => 'required|string|max:50',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'intervalo' => 'required|string|max:10',
            'etiqueta' => 'required|string|max:50',
            'proceso_id' => 'nullable|exists:procesos,uuid',
            'usuario_id' => 'required|exists:usuarios,id',
            'brigada_id' => 'nullable|exists:brigadas,uuid',
            'usuario_medico_uuid' => 'nullable|exists:usuarios,uuid', // ✅ NUEVO CAMPO
        ]);

        // ✅ RESOLVER UUIDs A IDs PARA GUARDAR EN BD
        if (!empty($validated['proceso_id'])) {
            $proceso = \App\Models\Proceso::where('uuid', $validated['proceso_id'])->first();
            $validated['proceso_id'] = $proceso ? $proceso->id : null;
        }
        
        if (!empty($validated['brigada_id'])) {
            $brigada = \App\Models\Brigada::where('uuid', $validated['brigada_id'])->first();
            $validated['brigada_id'] = $brigada ? $brigada->id : null;
        }

        // ✅ NUEVO: Log para verificar usuario_medico_id
        Log::info('✅ usuario_medico_id después de validación', [
            'usuario_medico_id' => $validated['usuario_medico_id'] ?? 'null',
            'type' => gettype($validated['usuario_medico_id'] ?? null)
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

        // ✅ LOG ANTES DE CREAR
        Log::info('📝 Creando agenda con datos', [
            'validated_data' => $validated,
            'usuario_medico_id_final' => $validated['usuario_medico_id'] ?? 'null'
        ]);

        $agenda = Agenda::create($validated);
        $agenda->load(['sede', 'proceso', 'usuario', 'brigada', 'usuarioMedico']); // ✅ CARGAR RELACIÓN

        // ✅ LOG DESPUÉS DE CREAR
        Log::info('✅ Agenda creada', [
            'id' => $agenda->id,
            'uuid' => $agenda->uuid,
            'usuario_medico_id_saved' => $agenda->usuario_medico_id,
            'usuario_medico_loaded' => $agenda->usuarioMedico ? $agenda->usuarioMedico->nombre_completo : 'null'
        ]);

        return response()->json([
            'success' => true,
            'data' => $agenda,
            'message' => 'Agenda creada exitosamente'
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('❌ Error de validación en agenda', [
            'errors' => $e->errors(),
            'input' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Datos de validación incorrectos',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('💥 Error crítico creando agenda', [
            'error' => $e->getMessage(),
            'input' => $request->all(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}

public function show(string $uuid): JsonResponse
{
    try {
        $agenda = Agenda::where('uuid', $uuid)->first();
        
        if (!$agenda) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda no encontrada'
            ], 404);
        }

        // ✅ CARGAR USUARIO CON DATOS COMPLETOS
        $agenda->load([
            'sede', 
            'proceso', 
            'usuario' => function ($query) {
                // ✅ ASEGURAR QUE SE CARGUEN TODOS LOS CAMPOS DEL USUARIO
                $query->select([
                    'id', 'uuid', 'nombre', 'apellido', 
                    'documento', 'correo', 'login'
                ]);
            },
            'brigada', 
            'citas' => function ($query) {
                $query->with([
                    'paciente' => function ($q) {
                        $q->select([
                            'id', 'uuid', 'documento', 
                            'primer_nombre', 'segundo_nombre',
                            'primer_apellido', 'segundo_apellido'
                        ]);
                    }
                ])
                ->whereNotIn('estado', ['CANCELADA'])
                ->orderBy('fecha_inicio');
            }
        ]);

        // ✅ PROCESAR DATOS DEL USUARIO
        if ($agenda->usuario) {
            $agenda->usuario->nombre_completo = trim(
                ($agenda->usuario->nombre ?? '') . ' ' . ($agenda->usuario->apellido ?? '')
            ) ?: 'Usuario del Sistema';
        }

        // ✅ PROCESAR DATOS DE CITAS
        if ($agenda->citas) {
            $agenda->citas->each(function ($cita) {
                if ($cita->paciente) {
                    $cita->paciente->nombre_completo = trim(
                        ($cita->paciente->primer_nombre ?? '') . ' ' .
                        ($cita->paciente->segundo_nombre ?? '') . ' ' .
                        ($cita->paciente->primer_apellido ?? '') . ' ' .
                        ($cita->paciente->segundo_apellido ?? '')
                    );
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $agenda,
            'message' => 'Agenda obtenida exitosamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error obteniendo agenda', [
            'uuid' => $uuid,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
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
   
public function contarCitas($uuid): JsonResponse
{
    try {
        // ✅ Buscar la agenda por UUID
        $agenda = Agenda::where('uuid', $uuid)->first();
        
        if (!$agenda) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda no encontrada'
            ], 404);
        }

        // ✅ CORRECCIÓN: Usar el ID de la agenda, no el UUID
        $count = Cita::where('agenda_id', $agenda->id) // ← CAMBIO AQUÍ
            ->whereNotIn('estado', ['CANCELADA', 'NO_ASISTIO'])
            ->count();

        // Calcular cupos totales
        $cuposTotales = $this->calcularCuposTotales($agenda);
        $cuposDisponibles = max(0, $cuposTotales - $count);

        return response()->json([
            'success' => true,
            'data' => [
                'agenda_uuid' => $uuid,
                'citas_count' => $count,
                'total_cupos' => $cuposTotales,
                'cupos_disponibles' => $cuposDisponibles
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error contando citas de agenda', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}

    /**
     * Calcular cupos totales de una agenda
     */
    private function calcularCuposTotales($agenda): int
    {
        try {
            $inicio = \Carbon\Carbon::parse($agenda->hora_inicio);
            $fin = \Carbon\Carbon::parse($agenda->hora_fin);
            $intervalo = (int) ($agenda->intervalo ?? 15);
            
            $duracionMinutos = $fin->diffInMinutes($inicio);
            return floor($duracionMinutos / $intervalo);
        } catch (\Exception $e) {
            \Log::warning('Error calculando cupos totales', [
                'agenda_id' => $agenda->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
 * ✅ NUEVO: Obtener citas de una agenda
 */
public function getCitas(string $uuid): JsonResponse
{
    try {
        $agenda = Agenda::where('uuid', $uuid)->first();
        
        if (!$agenda) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda no encontrada'
            ], 404);
        }

        $citas = $agenda->citas()
            ->with([
                'paciente' => function ($q) {
                    $q->select([
                        'id', 'uuid', 'documento',
                        'primer_nombre', 'segundo_nombre', 
                        'primer_apellido', 'segundo_apellido'
                    ]);
                }
            ])
            ->whereNotIn('estado', ['CANCELADA'])
            ->orderBy('fecha_inicio')
            ->get();

        // ✅ PROCESAR NOMBRES COMPLETOS
        $citas->each(function ($cita) {
            if ($cita->paciente) {
                $cita->paciente->nombre_completo = trim(
                    ($cita->paciente->primer_nombre ?? '') . ' ' .
                    ($cita->paciente->segundo_nombre ?? '') . ' ' .
                    ($cita->paciente->primer_apellido ?? '') . ' ' .
                    ($cita->paciente->segundo_apellido ?? '')
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $citas,
            'message' => 'Citas obtenidas exitosamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error obteniendo citas de agenda', [
            'uuid' => $uuid,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}


/**
 * ✅ NUEVO: Contar citas de una agenda
 */
public function getCitasCount(string $uuid): JsonResponse
{
    try {
        $agenda = Agenda::where('uuid', $uuid)->first();
        
        if (!$agenda) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda no encontrada'
            ], 404);
        }

        // Contar citas activas
        $citasCount = $agenda->citas()
            ->whereNotIn('estado', ['CANCELADA', 'NO_ASISTIO'])
            ->count();

        // Calcular cupos totales
        $inicio = \Carbon\Carbon::parse($agenda->hora_inicio);
        $fin = \Carbon\Carbon::parse($agenda->hora_fin);
        $intervalo = $agenda->intervalo ?? 15;
        
        $duracionMinutos = $fin->diffInMinutes($inicio);
        $totalCupos = floor($duracionMinutos / $intervalo);
        $cuposDisponibles = max(0, $totalCupos - $citasCount);

        return response()->json([
            'success' => true,
            'data' => [
                'citas_count' => $citasCount,
                'total_cupos' => $totalCupos,
                'cupos_disponibles' => $cuposDisponibles,
                'duracion_minutos' => $duracionMinutos,
                'intervalo' => $intervalo
            ],
            'message' => 'Conteo de citas obtenido exitosamente'
        ]);

    } catch (\Exception $e) {
        Log::error('Error contando citas de agenda', [
            'uuid' => $uuid,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}

}