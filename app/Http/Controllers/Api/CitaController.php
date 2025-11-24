<?php
// app/Http/Controllers/Api/CitaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Cita;
use App\Models\Proceso; 
use App\Models\CategoriaCups; 
use App\Models\CupsContratado;
use App\Models\Agenda;
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

                // ✅ VALIDACIÓN MEJORADA - cups_contratado_uuid ahora es OPCIONAL
                $validatedData = $request->validate([
                    'sede_id' => 'nullable|exists:sedes,id',
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
                    'cups_contratado_uuid' => 'nullable|string|exists:cups_contratados,uuid' // ✅ AHORA OPCIONAL
                ]);

                // ✅ NUEVO: ASIGNAR AUTOMÁTICAMENTE EL CUPS CORRECTO
                $resultadoCups = $this->asignarCupsAutomatico(
                    $validatedData['paciente_uuid'],
                    $validatedData['agenda_uuid']
                );

                if (!$resultadoCups['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $resultadoCups['error'],
                        'requiere_medicina_general' => $resultadoCups['requiere_medicina_general'] ?? false
                    ], 422);
                }

                // ✅ ASIGNAR EL CUPS AUTOMÁTICAMENTE
                $validatedData['cups_contratado_uuid'] = $resultadoCups['cups_contratado_uuid'];

                // Completar datos
                $validatedData['sede_id'] = $validatedData['sede_id'] ?? $request->user()->sede_id;
                $validatedData['usuario_creo_cita_id'] = $request->user()->id;
                $validatedData['estado'] = $validatedData['estado'] ?? 'PROGRAMADA';

                Log::info('📝 Datos validados para crear cita', [
                    'data' => $validatedData,
                    'cups_asignado' => $resultadoCups['cups_nombre'],
                    'tipo_consulta' => $resultadoCups['tipo_consulta']
                ]);

                $cita = Cita::create($validatedData);
                
                $cita->load([
                    'paciente', 
                    'agenda', 
                    'cupsContratado.categoriaCups', 
                    'usuarioCreador',
                    'sede'
                ]);

                Log::info('✅ Cita creada exitosamente con CUPS automático', [
                    'cita_uuid' => $cita->uuid,
                    'paciente_uuid' => $cita->paciente_uuid,
                    'cups_asignado' => $resultadoCups['cups_nombre'],
                    'tipo_consulta' => $resultadoCups['tipo_consulta']
                ]);

                return response()->json([
                    'success' => true,
                    'data' => new CitaResource($cita),
                    'message' => 'Cita creada exitosamente',
                    'meta' => [
                        'cups_asignado' => $resultadoCups['cups_nombre'],
                        'tipo_consulta' => $resultadoCups['tipo_consulta']
                    ]
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

    /**
     * 🔧 MÉTODO CORREGIDO - Retorna ARRAY en lugar de STRING
     */
    private function asignarCupsAutomatico($pacienteUuid, $agendaUuid)
    {
        try {
            Log::info('🔍 Asignando CUPS automático', [
                'paciente_uuid' => $pacienteUuid,
                'agenda_uuid' => $agendaUuid
            ]);

            // 1. Obtener la agenda
            $agenda = Agenda::where('uuid', $agendaUuid)->first();
            
            if (!$agenda) {
                Log::warning('⚠️ Agenda no encontrada', ['agenda_uuid' => $agendaUuid]);
                return [
                    'success' => false,
                    'error' => 'Agenda no encontrada',
                    'cups_contratado_uuid' => null
                ];
            }

            // 2. Obtener proceso de la agenda (solo para logging)
            $proceso = $agenda->proceso;
            
            if (!$proceso) {
                Log::warning('⚠️ Agenda sin proceso asignado');
            } else {
                Log::info('✅ Proceso identificado', ['proceso_nombre' => $proceso->nombre]);
            }

            // 3. Determinar tipo de consulta (PRIMERA VEZ o CONTROL)
            $tipoConsulta = $this->determinarTipoConsulta($pacienteUuid, $agendaUuid);
            
            Log::info('✅ Tipo de consulta determinado', ['tipo_consulta' => $tipoConsulta]);

            // 4. Obtener categoría CUPS según tipo de consulta
            $categoriaCups = CategoriaCups::where('nombre', $tipoConsulta)->first();
            
            if (!$categoriaCups) {
                Log::warning('⚠️ Categoría CUPS no encontrada', ['tipo_consulta' => $tipoConsulta]);
                return [
                    'success' => false,
                    'error' => "No se encontró la categoría CUPS para '{$tipoConsulta}'",
                    'cups_contratado_uuid' => null,
                    'tipo_consulta' => $tipoConsulta
                ];
            }

            Log::info('✅ Categoría CUPS encontrada', [
                'tipo_consulta' => $tipoConsulta,
                'categoria_id' => $categoriaCups->id
            ]);

            // 5. 🔧 BUSCAR CUPS CONTRATADO - SIN proceso_id
            $cupsContratado = CupsContratado::where('categoria_cups_id', $categoriaCups->id)
                ->where('estado', 'ACTIVO')
                ->whereHas('contrato', function($query) {
                    $query->where('estado', 'ACTIVO')
                        ->where('fecha_inicio', '<=', now())
                        ->where('fecha_fin', '>=', now());
                })
                ->with(['cups', 'contrato'])
                ->first();

            if (!$cupsContratado) {
                Log::warning('⚠️ No se encontró CUPS contratado vigente', [
                    'categoria_cups_id' => $categoriaCups->id,
                    'tipo_consulta' => $tipoConsulta
                ]);
                return [
                    'success' => false,
                    'error' => "No hay CUPS contratado activo para '{$tipoConsulta}'",
                    'cups_contratado_uuid' => null,
                    'tipo_consulta' => $tipoConsulta
                ];
            }

            Log::info('✅ CUPS automático asignado', [
                'cups_contratado_uuid' => $cupsContratado->uuid,
                'cups_codigo' => $cupsContratado->cups->codigo ?? 'N/A',
                'cups_nombre' => $cupsContratado->cups->nombre ?? 'N/A',
                'contrato_numero' => $cupsContratado->contrato->numero ?? 'N/A'
            ]);

            // ✅ RETORNAR ARRAY CON ESTRUCTURA CONSISTENTE
            return [
                'success' => true,
                'cups_contratado_uuid' => $cupsContratado->uuid,
                'cups_codigo' => $cupsContratado->cups->codigo ?? 'N/A',
                'cups_nombre' => $cupsContratado->cups->nombre ?? 'N/A',
                'tipo_consulta' => $tipoConsulta,
                'contrato_numero' => $cupsContratado->contrato->numero ?? 'N/A'
            ];

        } catch (\Exception $e) {
            Log::error('💥 Error asignando CUPS automático', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Error interno al asignar CUPS: ' . $e->getMessage(),
                'cups_contratado_uuid' => null
            ];
        }
    }






    /**
     * ✅ DETERMINAR TIPO DE CONSULTA BASADO EN HISTORIAL DEL PACIENTE
     */
    private function determinarTipoConsulta(string $pacienteUuid, string $agendaUuid): string
    {
        try {
            Log::info('🔍 Determinando tipo de consulta', [
                'paciente_uuid' => $pacienteUuid,
                'agenda_uuid' => $agendaUuid
            ]);

            // 1️⃣ Obtener agenda y proceso
            $agenda = Agenda::with('proceso')->where('uuid', $agendaUuid)->first();
            
            if (!$agenda || !$agenda->proceso) {
                Log::warning('⚠️ Agenda o proceso no encontrado');
                return 'PRIMERA VEZ';
            }

            $procesoNombre = $agenda->proceso->nombre;
            
            Log::info('📋 Proceso identificado', [
                'proceso_nombre' => $procesoNombre
            ]);

            // 2️⃣ Mapear proceso funcional
            $procesoFuncional = $this->mapearProcesoFuncional($procesoNombre);

            Log::info('🔄 Proceso funcional mapeado', [
                'proceso_original' => $procesoNombre,
                'proceso_funcional' => $procesoFuncional
            ]);

            // 3️⃣ ✅ BUSCAR POR PROCESO ORIGINAL, NO POR EL MAPEADO
            $citasAnteriores = Cita::where('paciente_uuid', $pacienteUuid)
                ->whereHas('agenda.proceso', function ($query) use ($procesoNombre) {
                    // ✅ BUSCAR POR PROCESO ORIGINAL
                    $query->where('nombre', 'LIKE', "%{$procesoNombre}%");
                })
                ->whereIn('estado', ['ATENDIDA', 'PROGRAMADA'])
                ->where('fecha', '<', now()->format('Y-m-d'))
                ->count();

            Log::info('📊 Citas anteriores encontradas', [
                'paciente_uuid' => $pacienteUuid,
                'proceso_buscado' => $procesoNombre,  // ← CAMBIO AQUÍ
                'citas_anteriores' => $citasAnteriores
            ]);

            // 4️⃣ ✅ DETERMINAR TIPO DE CONSULTA
            $tipoConsulta = ($citasAnteriores > 0) ? 'CONTROL' : 'PRIMERA VEZ';
            
            Log::info('✅ Tipo de consulta determinado', [
                'tipo_consulta' => $tipoConsulta,
                'citas_previas' => $citasAnteriores
            ]);

            return $tipoConsulta;

        } catch (\Exception $e) {
            Log::error('❌ Error determinando tipo de consulta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 'PRIMERA VEZ';
        }
    }


    /**
     * ✅ MAPEAR PROCESO A SU EQUIVALENTE FUNCIONAL
     */
    private function mapearProcesoFuncional(string $procesoNombre): string
    {
        // Normalizar nombre (quitar espacios extras, convertir a mayúsculas)
        $procesoNombre = strtoupper(trim($procesoNombre));
        
        // Mapeo de procesos a su categoría funcional
        $mapeo = [
            // MEDICINA GENERAL
            'MEDICINA GENERAL' => 'MEDICINA GENERAL',
            'ESPECIAL CONTROL' => 'MEDICINA GENERAL',
            'ESPECIAL PRIMERA VEZ' => 'MEDICINA GENERAL',
            'CONSULTA EXTERNA' => 'MEDICINA GENERAL',
            'CONTROL MEDICO' => 'MEDICINA GENERAL',
            
            // PSICOLOGÍA
            'PSICOLOGIA' => 'PSICOLOGIA',
            'PSICOLOGÍA' => 'PSICOLOGIA',
            'PSICOLOGIA CONTROL' => 'PSICOLOGIA',
            'PSICOLOGIA PRIMERA VEZ' => 'PSICOLOGIA',
            
            // ENFERMERÍA
            'ENFERMERIA' => 'ENFERMERIA',
            'ENFERMERÍA' => 'ENFERMERIA',
            'ENFERMERIA CONTROL' => 'ENFERMERIA',
            'ENFERMERIA PRIMERA VEZ' => 'ENFERMERIA',
            
            // NUTRICIÓN
            'NUTRICION' => 'NUTRICION',
            'NUTRICIÓN' => 'NUTRICION',
            'NUTRICION CONTROL' => 'NUTRICION',
            'NUTRICION PRIMERA VEZ' => 'NUTRICION',
            
            // ODONTOLOGÍA
            'ODONTOLOGIA' => 'ODONTOLOGIA',
            'ODONTOLOGÍA' => 'ODONTOLOGIA',
            'ODONTOLOGIA CONTROL' => 'ODONTOLOGIA',
            'ODONTOLOGIA PRIMERA VEZ' => 'ODONTOLOGIA',
            
            // TRABAJO SOCIAL
            'TRABAJO SOCIAL' => 'TRABAJO SOCIAL',
            'TRABAJO SOCIAL CONTROL' => 'TRABAJO SOCIAL',
            'TRABAJO SOCIAL PRIMERA VEZ' => 'TRABAJO SOCIAL',
        ];
        
        // Buscar en el mapeo
        if (isset($mapeo[$procesoNombre])) {
            return $mapeo[$procesoNombre];
        }
        
        // Si no encuentra mapeo, buscar por coincidencia parcial
        foreach ($mapeo as $key => $value) {
            if (str_contains($procesoNombre, $key) || str_contains($key, $procesoNombre)) {
                return $value;
            }
        }
        
        // Por defecto, retornar el mismo nombre
        return $procesoNombre;
    }

    /**
     * ✅ NUEVO: Endpoint para determinar tipo de consulta SIN crear la cita
     */
    public function determinarTipoConsultaPrevio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'paciente_uuid' => 'required|string|exists:pacientes,uuid',
                'agenda_uuid' => 'required|string|exists:agendas,uuid'
            ]);

            Log::info('🔍 API: Determinando tipo de consulta previo', [
                'paciente_uuid' => $request->paciente_uuid,
                'agenda_uuid' => $request->agenda_uuid
            ]);

            // ✅ REUTILIZAR MÉTODO PRIVADO EXISTENTE
            $resultadoCups = $this->asignarCupsAutomatico(
                $request->paciente_uuid,
                $request->agenda_uuid
            );

            if (!$resultadoCups['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $resultadoCups['error'],
                    'requiere_medicina_general' => $resultadoCups['requiere_medicina_general'] ?? false
                ], 422);
            }

            // ✅ OBTENER AGENDA PARA INFORMACIÓN ADICIONAL
            $agenda = Agenda::with('proceso')->where('uuid', $request->agenda_uuid)->first();
            
            Log::info('✅ Tipo de consulta determinado exitosamente', [
                'tipo_consulta' => $resultadoCups['tipo_consulta'],
                'cups_codigo' => $resultadoCups['cups_codigo'],
                'cups_nombre' => $resultadoCups['cups_nombre']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'tipo_consulta' => $resultadoCups['tipo_consulta'],
                    'cups_recomendado' => [
                        'uuid' => $resultadoCups['cups_contratado_uuid'],
                        'codigo' => $resultadoCups['cups_codigo'],
                        'nombre' => $resultadoCups['cups_nombre'],
                        'categoria' => $resultadoCups['tipo_consulta']
                    ],
                    'proceso_nombre' => $agenda->proceso->nombre ?? 'N/A',
                    'mensaje' => $this->generarMensajeTipoConsulta(
                        $resultadoCups['tipo_consulta'],
                        $agenda->proceso->nombre ?? 'MEDICINA GENERAL'
                    )
                ],
                'message' => 'Tipo de consulta determinado exitosamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ Error determinando tipo de consulta previo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ GENERAR MENSAJE INFORMATIVO
     */
    private function generarMensajeTipoConsulta(string $tipoConsulta, string $procesoNombre): string
    {
        if ($tipoConsulta === 'PRIMERA VEZ') {
            return "Esta será la primera consulta de {$procesoNombre} para este paciente.";
        } else {
            return "El paciente ya tiene consultas previas de {$procesoNombre}. Esta será una consulta de CONTROL.";
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
            Log::info('🔍 Validando especialidad', [
                'paciente_uuid' => $pacienteUuid,
                'agenda_uuid' => $agendaUuid
            ]);

            // ✅ CORRECTO: Usar 'estado' = 'ACTIVO' en lugar de 'activo' = 1
            $agenda = \App\Models\Agenda::where('uuid', $agendaUuid)
                ->where('estado', 'ACTIVO') // ← ✅ CAMBIO AQUÍ
                ->with('proceso')
                ->first();

            if (!$agenda) {
                Log::error('❌ Agenda no encontrada o no activa', [
                    'agenda_uuid' => $agendaUuid
                ]);
                
                return [
                    'success' => false,
                    'error' => 'La agenda seleccionada no está disponible o fue anulada'
                ];
            }

            if (!$agenda->proceso) {
                return [
                    'success' => false,
                    'error' => 'La agenda no tiene un proceso/especialidad asignado'
                ];
            }

            $procesoNombre = strtoupper(trim($agenda->proceso->nombre));
            
            Log::info('✅ Agenda válida', [
                'agenda_uuid' => $agendaUuid,
                'proceso' => $procesoNombre,
                'estado' => $agenda->estado
            ]);

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
                return strtoupper(trim($cita->agenda->proceso->nombre ?? '')) === 'MEDICINA GENERAL' &&
                    $cita->cupsContratado && 
                    $cita->cupsContratado->categoriaCups &&
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
            
            if (in_array($procesoNombre, $especialidadesSoloControl)) {
                return [
                    'success' => true,
                    'tipo_consulta' => 'CONTROL'
                ];
            }

            $citasDeEspecialidad = $citasDelPaciente->filter(function($cita) use ($procesoNombre) {
                return strtoupper(trim($cita->agenda->proceso->nombre ?? '')) === $procesoNombre;
            });

            $tipoConsulta = $citasDeEspecialidad->isEmpty() ? 'PRIMERA VEZ' : 'CONTROL';

            return [
                'success' => true,
                'tipo_consulta' => $tipoConsulta
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error validando especialidad', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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
    public function citasPaciente(string $pacienteUuid): JsonResponse
    {
        try {
            Log::info('📋 API CitaController@citasPaciente - Inicio', [
                'paciente_uuid' => $pacienteUuid
            ]);

            // ✅ BUSCAR PACIENTE POR UUID
            $paciente = Paciente::where('uuid', $pacienteUuid)->first();

            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ], 404);
            }

            // ✅ EAGER LOADING CON CAMELCASE (CORREGIDO)
            $citas = Cita::with([
                'paciente',
                'agenda.proceso',
                'agenda.usuarioMedico.especialidad',
                'cupsContratado.categoriaCups',  // ✅ CAMBIADO A CAMELCASE
                'cupsContratado.cups',            // ✅ CAMBIADO A CAMELCASE
                'usuarioCreador',
                'sede'
            ])
            ->where('paciente_uuid', $paciente->uuid)
            ->whereIn('estado', ['PROGRAMADA', 'ATENDIDA', 'CONFIRMADA', 'EN_ATENCION'])
            ->orderBy('fecha', 'desc')
            ->orderBy('fecha_inicio', 'desc')
            ->get();

            Log::info('✅ Citas obtenidas', [
                'total' => $citas->count()
            ]);

            // ✅ TRANSFORMAR CITAS CON CAMELCASE (CORREGIDO)
            $citasConInfo = $citas->map(function($cita) {
                return [
                    'uuid' => $cita->uuid,
                    'fecha' => $cita->fecha,
                    'fecha_inicio' => $cita->fecha_inicio,
                    'fecha_final' => $cita->fecha_final,
                    'hora' => $cita->fecha_inicio ? \Carbon\Carbon::parse($cita->fecha_inicio)->format('H:i') : null,
                    'estado' => $cita->estado,
                    'observaciones' => $cita->nota,
                    'motivo_consulta' => $cita->motivo,
                    
                    // PACIENTE
                    'paciente_uuid' => $cita->paciente?->uuid,
                    'paciente_nombre' => $cita->paciente?->nombre_completo ?? 'N/A',
                    'paciente_documento' => $cita->paciente?->documento ?? 'N/A',
                    
                    // AGENDA Y PROCESO
                    'agenda_uuid' => $cita->agenda?->uuid,
                    'consultorio' => $cita->agenda?->consultorio,
                    'proceso_nombre' => $cita->agenda?->proceso?->nombre ?? 'N/A',
                    
                    // MÉDICO
                    'medico_uuid' => $cita->agenda?->usuarioMedico?->uuid,
                    'medico_nombre' => $cita->agenda?->usuarioMedico?->nombre_completo 
                        ?? $cita->agenda?->usuarioMedico?->name 
                        ?? 'N/A',
                    'medico_especialidad' => $cita->agenda?->usuarioMedico?->especialidad?->nombre ?? 'N/A',
                    
                    // ✅ CUPS Y CATEGORÍA CON CAMELCASE (CORREGIDO)
                    'cups_contratado_uuid' => $cita->cupsContratado?->uuid,
                    'cups_codigo' => $cita->cupsContratado?->cups?->codigo ?? 'N/A',
                    'cups_nombre' => $cita->cupsContratado?->cups?->nombre ?? 'N/A',
                    'categoria_cups_id' => $cita->cupsContratado?->categoriaCups?->id,
                    'categoria_cups_nombre' => $cita->cupsContratado?->categoriaCups?->nombre ?? 'N/A',
                    
                    // SEDE
                    'sede_nombre' => $cita->sede?->nombre ?? 'N/A',
                    
                    // CREADOR
                    'creado_por' => $cita->usuarioCreador?->nombre_completo 
                        ?? $cita->usuarioCreador?->name 
                        ?? 'Sistema',
                    
                    'created_at' => $cita->created_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $citasConInfo,
                'meta' => [
                    'paciente_uuid' => $pacienteUuid,
                    'paciente_nombre' => $paciente->nombre_completo,
                    'paciente_documento' => $paciente->documento,
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
