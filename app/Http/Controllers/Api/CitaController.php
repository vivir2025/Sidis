<?php
// app/Http/Controllers/Api/CitaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Cita;
use App\Models\Paciente;
use App\Http\Resources\CitaResource;
use Illuminate\Support\Facades\Log;

class CitaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // ✅ CAMBIO PRINCIPAL: Igual que AgendaController - SIN filtro automático de sede
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
            'sede'
        ]);

        // ✅ FILTRO DE SEDE OPCIONAL (igual que en AgendaController)
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        // Filtros existentes
        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('paciente_documento')) {
            $query->whereHas('paciente', function ($q) use ($request) {
                $q->where('documento', $request->paciente_documento);
            });
        }

        if ($request->filled('paciente_uuid')) {
            $query->where('paciente_uuid', $request->paciente_uuid);
        }

        if ($request->filled('agenda_uuid')) {
            $query->where('agenda_uuid', $request->agenda_uuid);
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
        }

        // ✅ BÚSQUEDA MEJORADA (similar a AgendaController)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motivo', 'like', "%{$search}%")
                  ->orWhere('nota', 'like', "%{$search}%")
                  ->orWhere('patologia', 'like', "%{$search}%")
                  ->orWhereHas('paciente', function ($pq) use ($search) {
                      $pq->where('documento', 'like', "%{$search}%")
                         ->orWhere('primer_nombre', 'like', "%{$search}%")
                         ->orWhere('primer_apellido', 'like', "%{$search}%");
                  });
            });
        }

        // ✅ ORDENAMIENTO MEJORADO (similar a AgendaController)
        $sortBy = $request->get('sort_by', 'fecha_inicio');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSortFields = ['fecha', 'fecha_inicio', 'estado', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'fecha_inicio';
        }
        
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
        
        if ($sortBy === 'fecha_inicio') {
            $query->orderBy('fecha_inicio', $sortOrder)
                  ->orderBy('fecha', $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy($sortBy, $sortOrder)
                  ->orderBy('fecha_inicio', 'desc');
        }

        // ✅ PAGINACIÓN MEJORADA
        $perPage = $request->get('per_page', 15);
        $perPage = max(5, min(100, (int) $perPage));
        
        $citas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'meta' => [
                'current_page' => $citas->currentPage(),
                'last_page' => $citas->lastPage(),
                'per_page' => $citas->perPage(),
                'total' => $citas->total()
            ],
            'message' => 'Citas obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('🩺 API CitaController@store - Datos recibidos', [
                'data' => $request->all(),
                'user_id' => $request->user()->id
            ]);

            // ✅ VALIDACIÓN MEJORADA - Permitir sede_id opcional
            $validatedData = $request->validate([
                'sede_id' => 'nullable|exists:sedes,id', // ✅ NUEVO: Permitir especificar sede
                'fecha' => 'required|date',
                'fecha_inicio' => 'required|date',
                'fecha_final' => 'required|date|after:fecha_inicio',
                'fecha_deseada' => 'nullable|date',
                'motivo' => 'nullable|string|max:200',
                'nota' => 'required|string|max:200',
                'estado' => 'nullable|string|max:50',
                'patologia' => 'nullable|string|max:50',
                'paciente_uuid' => 'required|string|exists:pacientes,uuid',
                'agenda_uuid' => 'required|string|exists:agendas,uuid',
                'cups_contratado_uuid' => 'required|string|exists:cups_contratados,uuid'
            ]);
              // ✅ VALIDAR ESPECIALIDAD ANTES DE CREAR
        $validacionEspecialidad = $this->validarEspecialidadParaPaciente(
            $validatedData['paciente_uuid'],
            $validatedData['agenda_uuid']
        );

        if (!$validacionEspecialidad['success']) {
            return response()->json([
                'success' => false,
                'message' => $validacionEspecialidad['error'],
                'requiere_medicina_general' => $validacionEspecialidad['requiere_medicina_general'] ?? false
            ], 422);
        }


            // ✅ CAMBIO: Usar sede del request o del usuario como fallback
            $validatedData['sede_id'] = $validatedData['sede_id'] ?? $request->user()->sede_id;
            $validatedData['usuario_creo_cita_id'] = $request->user()->id;
            $validatedData['estado'] = $validatedData['estado'] ?? 'PROGRAMADA';

            Log::info('📝 Datos validados para crear cita', [
                'data' => $validatedData,
                'sede_id_final' => $validatedData['sede_id']
            ]);

            $cita = Cita::create($validatedData);
            
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador',
                'sede'
            ]);

            Log::info('✅ Cita creada exitosamente en API', [
                'cita_uuid' => $cita->uuid,
                'paciente_uuid' => $cita->paciente_uuid,
                'sede_id' => $cita->sede_id
            ]);

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita creada exitosamente'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Errores de validación en cita', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('💥 Error creando cita en API', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)
                ->with([
                    'paciente', 
                    'agenda', 
                    'cupsContratado',
                    'usuarioCreador',
                    'sede'
                ])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cita no encontrada'
            ], 404);
        }
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)->firstOrFail();
            
            $validatedData = $request->validate([
                'sede_id' => 'sometimes|exists:sedes,id', // ✅ NUEVO: Permitir cambiar sede
                'fecha' => 'sometimes|date',
                'fecha_inicio' => 'sometimes|date',
                'fecha_final' => 'sometimes|date|after:fecha_inicio',
                'fecha_deseada' => 'nullable|date',
                'motivo' => 'nullable|string|max:200',
                'nota' => 'sometimes|string|max:200',
                'estado' => 'sometimes|string|max:50',
                'patologia' => 'nullable|string|max:50',
                'paciente_uuid' => 'sometimes|string|exists:pacientes,uuid',
                'agenda_uuid' => 'sometimes|string|exists:agendas,uuid',
                'cups_contratado_uuid' => 'nullable|string|exists:cups_contratados,uuid',
            ]);

            $cita->update($validatedData);
            
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador',
                'sede'
            ]);

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)->firstOrFail();
            $cita->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cita eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function citasDelDia(Request $request): JsonResponse
    {
        $fecha = $request->get('fecha', now()->format('Y-m-d'));
        
        // ✅ CAMBIO: Sin filtro automático de sede
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador'
        ]);

        // ✅ FILTRO OPCIONAL DE SEDE
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        $citas = $query->whereDate('fecha', $fecha)
            ->orderBy('fecha_inicio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'meta' => [
                'fecha' => $fecha,
                'total_citas' => $citas->count()
            ],
            'message' => 'Citas del día obtenidas exitosamente'
        ]);
    }
    /**
 * ✅ NUEVO: Validar especialidad en backend
 */
private function validarEspecialidadParaPaciente(string $pacienteUuid, string $agendaUuid): array
{
    try {
        // ✅ OBTENER AGENDA
        $agenda = \App\Models\Agenda::where('uuid', $agendaUuid)
            ->with('proceso')
            ->first();

        if (!$agenda || !$agenda->proceso) {
            return [
                'success' => false,
                'error' => 'Agenda o proceso no encontrado'
            ];
        }

        $procesoNombre = $agenda->proceso->nombre;

        // ✅ OBTENER PACIENTE
        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();

        if (!$paciente) {
            return [
                'success' => false,
                'error' => 'Paciente no encontrado'
            ];
        }

        // ✅ OBTENER CITAS DEL PACIENTE
        $citasDelPaciente = \App\Models\Cita::where('paciente_uuid', $paciente->uuid)
            ->whereIn('estado', ['PROGRAMADA', 'ATENDIDA', 'CONFIRMADA'])
            ->with(['agenda.proceso', 'cupsContratado.categoriaCups'])
            ->get();

        // ✅ SI NO TIENE CITAS, SOLO PUEDE MEDICINA GENERAL
        if ($citasDelPaciente->isEmpty()) {
            if ($procesoNombre !== 'MEDICINA GENERAL') {
                return [
                    'success' => false,
                    'error' => 'El paciente debe tener primero una cita de MEDICINA GENERAL - PRIMERA VEZ',
                    'requiere_medicina_general' => true
                ];
            }

            return [
                'success' => true,
                'tipo_consulta' => 'PRIMERA VEZ'
            ];
        }

        // ✅ VERIFICAR PRIMERA VEZ DE MEDICINA GENERAL
        $tienePrimeraVezMG = $citasDelPaciente->contains(function($cita) {
            return $cita->agenda->proceso->nombre === 'MEDICINA GENERAL' &&
                   $cita->cupsContratado->categoriaCups->id == 1;
        });

        if (!$tienePrimeraVezMG && $procesoNombre !== 'MEDICINA GENERAL') {
            return [
                'success' => false,
                'error' => 'El paciente debe tener MEDICINA GENERAL - PRIMERA VEZ antes de otras especialidades',
                'requiere_medicina_general' => true
            ];
        }

        // ✅ DETERMINAR TIPO DE CONSULTA
        $especialidadesSoloControl = ['NEFROLOGIA', 'MEDICINA INTERNA', 'INTERNISTA'];
        
        if (in_array(strtoupper($procesoNombre), $especialidadesSoloControl)) {
            return [
                'success' => true,
                'tipo_consulta' => 'CONTROL'
            ];
        }

        $citasDeEspecialidad = $citasDelPaciente->filter(function($cita) use ($procesoNombre) {
            return strtoupper($cita->agenda->proceso->nombre) === strtoupper($procesoNombre);
        });

        $tipoConsulta = $citasDeEspecialidad->isEmpty() ? 'PRIMERA VEZ' : 'CONTROL';

        return [
            'success' => true,
            'tipo_consulta' => $tipoConsulta
        ];

    } catch (\Exception $e) {
        Log::error('Error validando especialidad en backend', [
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => 'Error interno validando especialidad'
        ];
    }
}

    // ✅ NUEVOS MÉTODOS SIMILARES A AgendaController
    public function citasPorPaciente(Request $request, string $pacienteUuid): JsonResponse
    {
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
            'sede'
        ])->where('paciente_uuid', $pacienteUuid);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        $citas = $query->orderBy('fecha_inicio', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'message' => 'Citas del paciente obtenidas exitosamente'
        ]);
    }

    public function citasPorAgenda(Request $request, string $agendaUuid): JsonResponse
    {
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
            'sede'
        ])->where('agenda_uuid', $agendaUuid);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        $citas = $query->orderBy('fecha_inicio')->get();

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'message' => 'Citas de la agenda obtenidas exitosamente'
        ]);
    }

    /**
 * ✅ OBTENER CITAS DE UNA AGENDA ESPECÍFICA
 */
public function citasDeAgenda(string $agendaUuid, Request $request): JsonResponse
{
    try {
        Log::info('🔍 API CitasDeAgenda solicitadas', [
            'agenda_uuid' => $agendaUuid,
            'filtros' => $request->all()
        ]);

        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
            'sede'
        ])->where('agenda_uuid', $agendaUuid);

        // ✅ FILTRO DE FECHA (CRÍTICO)
        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
            Log::info('🔍 Filtro fecha aplicado', [
                'fecha' => $request->fecha
            ]);
        }

        // ✅ FILTRO DE SEDE OPCIONAL
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        // ✅ FILTRO DE ESTADO
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // ✅ ORDENAR POR HORA
        $citas = $query->orderBy('fecha_inicio')->get();

        Log::info('✅ Citas de agenda obtenidas', [
            'agenda_uuid' => $agendaUuid,
            'total_encontradas' => $citas->count(),
            'fecha_filtro' => $request->fecha
        ]);

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'meta' => [
                'agenda_uuid' => $agendaUuid,
                'fecha' => $request->fecha,
                'total' => $citas->count()
            ],
            'message' => 'Citas de agenda obtenidas exitosamente'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo citas de agenda', [
            'agenda_uuid' => $agendaUuid,
            'error' => $e->getMessage(),
            'filtros' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error obteniendo citas de agenda',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function cambiarEstado(Request $request, string $uuid): JsonResponse
{
    try {
        Log::info('🔄 API CitaController@cambiarEstado - Solicitud recibida', [
            'cita_uuid' => $uuid,
            'nuevo_estado' => $request->estado,
            'method' => $request->method(),
            'all_data' => $request->all()
        ]);

        // ✅ BUSCAR LA CITA
        $cita = Cita::where('uuid', $uuid)->firstOrFail();

        Log::info('📋 Cita encontrada para cambio de estado', [
            'cita_uuid' => $cita->uuid,
            'estado_actual' => $cita->estado,
            'paciente' => $cita->paciente->primer_nombre ?? 'N/A'
        ]);

        // ✅ VALIDAR EL NUEVO ESTADO
        $validatedData = $request->validate([
            'estado' => 'required|string|in:PROGRAMADA,ATENDIDA,CANCELADA,NO_ASISTIO,REPROGRAMADA,EN_ATENCION'
        ]);

        $estadoAnterior = $cita->estado;
        $nuevoEstado = $validatedData['estado'];

        // ✅ ACTUALIZAR EL ESTADO
        $cita->update([
            'estado' => $nuevoEstado
        ]);

        // ✅ RECARGAR LA CITA CON RELACIONES
        $cita->load([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
            'sede'
        ]);

        Log::info('✅ Estado de cita cambiado exitosamente', [
            'cita_uuid' => $cita->uuid,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $nuevoEstado,
            'usuario_id' => $request->user()->id ?? 'N/A'
        ]);

        return response()->json([
            'success' => true,
            'data' => new CitaResource($cita),
            'message' => "Estado cambiado de '{$estadoAnterior}' a '{$nuevoEstado}' exitosamente",
            'meta' => [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'fecha_cambio' => now()->toISOString()
            ]
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        Log::warning('❌ Cita no encontrada para cambio de estado', [
            'cita_uuid' => $uuid
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Cita no encontrada'
        ], 404);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning('❌ Datos inválidos para cambio de estado', [
            'cita_uuid' => $uuid,
            'errors' => $e->errors(),
            'input' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Estado inválido',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('💥 Error cambiando estado de cita', [
            'cita_uuid' => $uuid,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'input' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => $e->getMessage()
        ], 500);
    }
}

  /**
     * ✅ AGREGAR ESTE MÉTODO AQUÍ (DESPUÉS DE cambiarEstado Y ANTES DEL CIERRE DE LA CLASE)
     */
public function citasPaciente(string $pacienteUuid): JsonResponse
{
    try {
        Log::info('📋 API CitaController@citasPaciente - Inicio', [
            'paciente_uuid' => $pacienteUuid
        ]);

        $paciente = Paciente::where('uuid', $pacienteUuid)->first();

        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ], 404);
        }

        // ✅ OBTENER UNA CITA PARA DEBUG
        $citaDebug = Cita::with([
            'agenda.proceso',
            'cupsContratado.categoriaCups',
            'cupsContratado.cups'
        ])
        ->where('paciente_id', $paciente->id)
        ->first();

        Log::info('🔍 DEBUG: Primera cita del paciente', [
            'cita_uuid' => $citaDebug?->uuid,
            'tiene_agenda' => $citaDebug?->agenda ? 'SI' : 'NO',
            'agenda_id' => $citaDebug?->agenda_id,
            'tiene_proceso' => $citaDebug?->agenda?->proceso ? 'SI' : 'NO',
            'proceso_id' => $citaDebug?->agenda?->proceso_id,
            'proceso_nombre' => $citaDebug?->agenda?->proceso?->nombre,
            'tiene_cups_contratado' => $citaDebug?->cupsContratado ? 'SI' : 'NO',
            'cups_contratado_id' => $citaDebug?->cups_contratado_id,
            'tiene_categoria' => $citaDebug?->cupsContratado?->categoriaCups ? 'SI' : 'NO',
            'categoria_id' => $citaDebug?->cupsContratado?->categoria_cups_id,
            'categoria_nombre' => $citaDebug?->cupsContratado?->categoriaCups?->nombre,
        ]);

        // ✅ OBTENER TODAS LAS CITAS
        $citas = Cita::with([
            'paciente:id,uuid,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,tipo_documento_id,numero_documento',
            
            'agenda' => function($query) {
                $query->select('id', 'uuid', 'fecha', 'consultorio', 'proceso_id', 'usuario_medico_id')
                    ->with([
                        'proceso:id,nombre,descripcion',
                        'usuarioMedico:id,uuid,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,especialidad_id',
                        'usuarioMedico.especialidad:id,nombre'
                    ]);
            },
            
            'cupsContratado' => function($query) {
                $query->select('id', 'uuid', 'cups_id', 'contrato_id', 'categoria_cups_id', 'valor_particular', 'valor_contrato')
                    ->with([
                        'categoriaCups:id,nombre,descripcion',
                        'cups:id,uuid,codigo,nombre,origen',
                        'contrato:id,uuid,numero_contrato,empresa_id'
                    ]);
            },
            
            'usuarioCreador:id,uuid,primer_nombre,primer_apellido',
            'sede:id,nombre,direccion'
        ])
        ->where('paciente_id', $paciente->id)
        ->whereIn('estado', ['PROGRAMADA', 'ATENDIDA', 'CONFIRMADA', 'EN_ATENCION'])
        ->orderBy('fecha', 'desc')
        ->orderBy('fecha_inicio', 'desc')
        ->get();

        Log::info('✅ Citas obtenidas', [
            'total' => $citas->count(),
            'primera_cita_debug' => [
                'uuid' => $citas->first()?->uuid,
                'tiene_agenda' => $citas->first()?->agenda ? 'SI' : 'NO',
                'tiene_proceso' => $citas->first()?->agenda?->proceso ? 'SI' : 'NO',
                'proceso_nombre' => $citas->first()?->agenda?->proceso?->nombre,
                'tiene_cups' => $citas->first()?->cupsContratado ? 'SI' : 'NO',
                'categoria_nombre' => $citas->first()?->cupsContratado?->categoriaCups?->nombre,
            ]
        ]);

        // TRANSFORMAR CITAS
        $citasConInfo = $citas->map(function($cita) {
            $agenda = $cita->agenda;
            $proceso = $agenda?->proceso;
            $medico = $agenda?->usuarioMedico;
            $cupsContratado = $cita->cupsContratado;
            $cups = $cupsContratado?->cups;
            $categoriaCups = $cupsContratado?->categoriaCups;
            
            return [
                'uuid' => $cita->uuid,
                'fecha' => $cita->fecha,
                'fecha_inicio' => $cita->fecha_inicio,
                'fecha_final' => $cita->fecha_final,
                'hora' => $cita->fecha_inicio ? \Carbon\Carbon::parse($cita->fecha_inicio)->format('H:i') : null,
                'estado' => $cita->estado,
                'observaciones' => $cita->nota,
                'motivo_consulta' => $cita->motivo,
                'patologia' => $cita->patologia,
                
                // IDs DIRECTOS PARA DEBUG
                'agenda_id' => $cita->agenda_id,
                'cups_contratado_id' => $cita->cups_contratado_id,
                
                // AGENDA
                'agenda_uuid' => $agenda?->uuid,
                'consultorio' => $agenda?->consultorio,
                'fecha_agenda' => $agenda?->fecha,
                
                // PROCESO
                'proceso_id' => $proceso?->id,
                'proceso_nombre' => $proceso?->nombre ?? 'N/A',
                'proceso_descripcion' => $proceso?->descripcion,
                
                // MÉDICO
                'medico_id' => $medico?->id,
                'medico_uuid' => $medico?->uuid,
                'medico_nombre' => $medico 
                    ? trim("{$medico->primer_nombre} {$medico->segundo_nombre} {$medico->primer_apellido} {$medico->segundo_apellido}")
                    : 'N/A',
                'medico_especialidad' => $medico?->especialidad?->nombre ?? 'N/A',
                
                // CUPS
                'cups_contratado_uuid' => $cupsContratado?->uuid,
                'cups_codigo' => $cups?->codigo ?? 'N/A',
                'cups_nombre' => $cups?->nombre ?? 'N/A',
                'cups_origen' => $cups?->origen,
                
                // CATEGORÍA CUPS
                'categoria_cups_id' => $categoriaCups?->id,
                'categoria_cups_nombre' => $categoriaCups?->nombre ?? 'N/A',
                'categoria_cups_descripcion' => $categoriaCups?->descripcion,
                
                // SEDE
                'sede_id' => $cita->sede?->id,
                'sede_nombre' => $cita->sede?->nombre ?? 'N/A',
                
                // CREADOR
                'creado_por' => $cita->usuarioCreador 
                    ? trim("{$cita->usuarioCreador->primer_nombre} {$cita->usuarioCreador->primer_apellido}")
                    : 'Sistema',
                
                'created_at' => $cita->created_at?->toISOString(),
                'updated_at' => $cita->updated_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $citasConInfo,
            'meta' => [
                'paciente_uuid' => $pacienteUuid,
                'total_citas' => $citas->count(),
            ],
            'message' => 'Citas del paciente obtenidas exitosamente'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo citas del paciente', [
            'paciente_uuid' => $pacienteUuid,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error obteniendo citas del paciente',
            'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
        ], 500);
    }
}

}
