<?php
// app/Http/Controllers/Api/HistoriaClinicaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\{HistoriaClinica, Cita};
use Illuminate\Support\Facades\{DB, Log, Validator};

class HistoriaClinicaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = HistoriaClinica::with([
                'cita.paciente', 
                'cita.agenda.usuarioMedico',
                'cita.agenda.proceso',
                'historiaComplementaria',
                'historiaDiagnosticos.diagnostico', 
                'historiaMedicamentos.medicamento',
                'historiaRemisiones.remision', 
                'historiaCups.cups'
            ])->bySede($request->user()->sede_id);

            // ✅ FILTROS
            if ($request->filled('paciente_documento')) {
                $query->whereHas('cita.paciente', function ($q) use ($request) {
                    $q->where('documento', $request->paciente_documento);
                });
            }

            if ($request->filled('profesional_uuid')) {
                $query->whereHas('cita.agenda.usuarioMedico', function ($q) use ($request) {
                    $q->where('uuid', $request->profesional_uuid);
                });
            }

            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $query->whereHas('cita', function ($q) use ($request) {
                    $q->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
                });
            }

            $historias = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $this->transformHistorias($historias),
                'meta' => [
                    'current_page' => $historias->currentPage(),
                    'last_page' => $historias->lastPage(),
                    'per_page' => $historias->perPage(),
                    'total' => $historias->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historias clínicas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo historias clínicas'
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // ✅ VALIDACIÓN COMPLETA
            $validator = Validator::make($request->all(), [
                'cita_id' => 'required|exists:citas,id',
                'motivo_consulta' => 'required|string',
                'enfermedad_actual' => 'required|string',
                'peso' => 'nullable|numeric|min:0|max:999.99',
                'talla' => 'nullable|numeric|min:0|max:9.99',
                
                // ✅ DIAGNÓSTICOS OBLIGATORIOS
                'diagnosticos' => 'required|array|min:1',
                'diagnosticos.*.diagnostico_id' => 'required|exists:diagnosticos,id',
                'diagnosticos.*.tipo' => 'required|in:PRINCIPAL,SECUNDARIO',
                'diagnosticos.*.tipo_diagnostico' => 'required|string',
                'diagnosticos.*.observacion' => 'nullable|string',
                
                // ✅ MEDICAMENTOS OPCIONALES
                'medicamentos' => 'nullable|array',
                'medicamentos.*.medicamento_id' => 'required|exists:medicamentos,id',
                'medicamentos.*.cantidad' => 'required|string|max:10',
                'medicamentos.*.dosis' => 'required|string|max:500',
                'medicamentos.*.frecuencia' => 'nullable|string',
                'medicamentos.*.duracion' => 'nullable|string',
                
                // ✅ REMISIONES OPCIONALES
                'remisiones' => 'nullable|array',
                'remisiones.*.remision_id' => 'required|exists:remisiones,id',
                'remisiones.*.observacion' => 'nullable|string|max:500',
                'remisiones.*.prioridad' => 'nullable|in:ALTA,MEDIA,BAJA',
                
                // ✅ CUPS OPCIONALES
                'cups' => 'nullable|array',
                'cups.*.cups_id' => 'required|exists:cups,id',
                'cups.*.observacion' => 'nullable|string|max:500',
                
                // ✅ HISTORIA COMPLEMENTARIA
                'historia_complementaria' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ VERIFICAR CITA Y DATOS RELACIONADOS
            $cita = Cita::with([
                'paciente', 
                'agenda.usuarioMedico', 
                'agenda.proceso'
            ])->findOrFail($request->cita_id);

            // ✅ VERIFICAR QUE NO EXISTA HISTORIA PREVIA
            $historiaExistente = HistoriaClinica::where('cita_id', $cita->id)->first();
            if ($historiaExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cita ya tiene una historia clínica asociada'
                ], 422);
            }

            // ✅ CREAR HISTORIA CLÍNICA PRINCIPAL
            $dataHistoria = $request->only([
                'finalidad', 'acompanante', 'acu_telefono', 'acu_parentesco',
                'causa_externa', 'motivo_consulta', 'enfermedad_actual',
                'peso', 'talla', 'imc', 'clasificacion',
                // ... todos los campos de la migración
            ]);

            $dataHistoria['sede_id'] = $request->user()->sede_id;
            $dataHistoria['cita_id'] = $cita->id;

            // ✅ CALCULAR IMC SI HAY PESO Y TALLA
            if ($request->peso && $request->talla) {
                $dataHistoria['imc'] = round($request->peso / pow($request->talla, 2), 2);
            }

            $historia = HistoriaClinica::create($dataHistoria);

            // ✅ CREAR DIAGNÓSTICOS
            foreach ($request->diagnosticos as $diagnosticoData) {
                $historia->historiaDiagnosticos()->create([
                    'diagnostico_id' => $diagnosticoData['diagnostico_id'],
                    'tipo' => $diagnosticoData['tipo'],
                    'tipo_diagnostico' => $diagnosticoData['tipo_diagnostico'],
                    'observacion' => $diagnosticoData['observacion'] ?? null
                ]);
            }

            // ✅ CREAR MEDICAMENTOS
            if ($request->filled('medicamentos')) {
                foreach ($request->medicamentos as $medicamentoData) {
                    $historia->historiaMedicamentos()->create([
                        'medicamento_id' => $medicamentoData['medicamento_id'],
                        'cantidad' => $medicamentoData['cantidad'],
                        'dosis' => $medicamentoData['dosis'],
                        'frecuencia' => $medicamentoData['frecuencia'] ?? null,
                        'duracion' => $medicamentoData['duracion'] ?? null,
                        'observaciones' => $medicamentoData['observaciones'] ?? null
                    ]);
                }
            }

            // ✅ CREAR REMISIONES
            if ($request->filled('remisiones')) {
                foreach ($request->remisiones as $remisionData) {
                    $historia->historiaRemisiones()->create([
                        'remision_id' => $remisionData['remision_id'],
                        'observacion' => $remisionData['observacion'] ?? null,
                        'prioridad' => $remisionData['prioridad'] ?? 'MEDIA',
                        'estado' => 'PENDIENTE',
                        'fecha_remision' => now()->toDateString()
                    ]);
                }
            }

            // ✅ CREAR CUPS
            if ($request->filled('cups')) {
                foreach ($request->cups as $cupsData) {
                    $historia->historiaCups()->create([
                        'cups_id' => $cupsData['cups_id'],
                        'observacion' => $cupsData['observacion'] ?? null,
                        'cantidad' => $cupsData['cantidad'] ?? 1,
                        'estado' => 'PENDIENTE'
                    ]);
                }
            }

            // ✅ CREAR HISTORIA COMPLEMENTARIA
            if ($request->filled('historia_complementaria')) {
                $historia->historiaComplementaria()->create($request->historia_complementaria);
            }

            // ✅ CARGAR RELACIONES COMPLETAS
            $historia->load([
                'cita.paciente', 
                'cita.agenda.usuarioMedico',
                'cita.agenda.proceso',
                'historiaComplementaria',
                'historiaDiagnosticos.diagnostico', 
                'historiaMedicamentos.medicamento',
                'historiaRemisiones.remision', 
                'historiaCups.cups'
            ]);

            DB::commit();

            Log::info('✅ Historia clínica creada exitosamente', [
                'historia_uuid' => $historia->uuid,
                'cita_id' => $cita->id,
                'paciente_documento' => $cita->paciente->documento,
                'profesional' => $cita->agenda->usuarioMedico->nombre_completo ?? 'Sin asignar'
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->transformHistoria($historia),
                'message' => 'Historia clínica creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('💥 Error creando historia clínica', [
                'error' => $e->getMessage(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)
                ->with([
                    'cita.paciente', 
                    'cita.agenda.usuarioMedico',
                    'cita.agenda.proceso',
                    'historiaComplementaria',
                    'historiaDiagnosticos.diagnostico', 
                    'historiaMedicamentos.medicamento',
                    'historiaRemisiones.remision', 
                    'historiaCups.cups'
                ])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $this->transformHistoria($historia)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Historia clínica no encontrada'
            ], 404);
        }
    }

    public function historiasPaciente(Request $request, string $pacienteUuid): JsonResponse
    {
        try {
            $historias = HistoriaClinica::whereHas('cita.paciente', function ($q) use ($pacienteUuid) {
                $q->where('uuid', $pacienteUuid);
            })
            ->with([
                'cita.agenda.usuarioMedico',
                'cita.agenda.proceso',
                'historiaDiagnosticos.diagnostico'
            ])
            ->bySede($request->user()->sede_id)
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $this->transformHistorias($historias)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo historias del paciente'
            ], 500);
        }
    }

    // ✅ MÉTODOS AUXILIARES PARA TRANSFORMAR DATOS
    private function transformHistoria($historia)
    {
        return [
            'uuid' => $historia->uuid,
            'fecha_atencion' => $historia->created_at->format('Y-m-d H:i:s'),
            'motivo_consulta' => $historia->motivo_consulta,
            'enfermedad_actual' => $historia->enfermedad_actual,
            'peso' => $historia->peso,
            'talla' => $historia->talla,
            'imc' => $historia->imc,
            
            // ✅ DATOS DEL PACIENTE
            'paciente' => [
                'uuid' => $historia->cita->paciente->uuid,
                'documento' => $historia->cita->paciente->documento,
                'nombre_completo' => $historia->cita->paciente->nombre_completo,
                'edad' => $historia->cita->paciente->edad,
                'genero' => $historia->cita->paciente->genero
            ],
            
            // ✅ DATOS DEL PROFESIONAL
            'profesional' => $historia->cita->agenda->usuarioMedico ? [
                'uuid' => $historia->cita->agenda->usuarioMedico->uuid,
                'nombre_completo' => $historia->cita->agenda->usuarioMedico->nombre_completo,
                'especialidad' => $historia->cita->agenda->usuarioMedico->especialidad->nombre ?? null
            ] : null,
            
            // ✅ DATOS DE LA CITA Y AGENDA
            'cita' => [
                'uuid' => $historia->cita->uuid,
                'fecha' => $historia->cita->fecha->format('Y-m-d'),
                'hora_inicio' => $historia->cita->fecha_inicio->format('H:i'),
                'proceso' => $historia->cita->agenda->proceso->nombre ?? null
            ],
            
            // ✅ DIAGNÓSTICOS
            'diagnosticos' => $historia->historiaDiagnosticos->map(function ($hd) {
                return [
                    'uuid' => $hd->uuid,
                    'tipo' => $hd->tipo,
                    'tipo_diagnostico' => $hd->tipo_diagnostico,
                    'observacion' => $hd->observacion,
                    'diagnostico' => [
                        'codigo' => $hd->diagnostico->codigo,
                        'nombre' => $hd->diagnostico->nombre
                    ]
                ];
            }),
            
            // ✅ MEDICAMENTOS
            'medicamentos' => $historia->historiaMedicamentos->map(function ($hm) {
                return [
                    'uuid' => $hm->uuid,
                    'cantidad' => $hm->cantidad,
                    'dosis' => $hm->dosis,
                    'frecuencia' => $hm->frecuencia,
                    'duracion' => $hm->duracion,
                    'medicamento' => [
                        'nombre' => $hm->medicamento->nombre,
                        'principio_activo' => $hm->medicamento->principio_activo
                    ]
                ];
            }),
            
            // ✅ REMISIONES
            'remisiones' => $historia->historiaRemisiones->map(function ($hr) {
                return [
                    'uuid' => $hr->uuid,
                    'observacion' => $hr->observacion,
                    'prioridad' => $hr->prioridad,
                    'estado' => $hr->estado,
                    'remision' => [
                        'nombre' => $hr->remision->nombre,
                        'tipo' => $hr->remision->tipo
                    ]
                ];
            }),
            
            // ✅ CUPS
            'cups' => $historia->historiaCups->map(function ($hc) {
                return [
                    'uuid' => $hc->uuid,
                    'observacion' => $hc->observacion,
                    'cantidad' => $hc->cantidad,
                    'estado' => $hc->estado,
                    'cups' => [
                        'codigo' => $hc->cups->codigo,
                        'nombre' => $hc->cups->nombre
                    ]
                ];
            }),
            
            // ✅ HISTORIA COMPLEMENTARIA
            'historia_complementaria' => $historia->historiaComplementaria
        ];
    }

    private function transformHistorias($historias)
    {
        return $historias->map(function ($historia) {
            return $this->transformHistoria($historia);
        });
    }
}
