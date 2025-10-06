<?php
// app/Http/Controllers/Api/HistoriaClinicaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\Diagnostico;
use App\Models\Medicamento;
use App\Models\Cups;
use App\Models\Remision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDF;

class HistoriaClinicaController extends Controller
{
    /**
     * Listar historias clínicas con filtros
     */
    public function index(Request $request)
    {
        try {
            $query = HistoriaClinica::with([
                'paciente',
                'medico',
                'sede',
                'diagnosticos.diagnostico',
                'medicamentos.medicamento'
            ]);

            // Filtros
            if ($request->has('paciente_id')) {
                $query->where('paciente_id', $request->paciente_id);
            }

            if ($request->has('medico_id')) {
                $query->where('medico_id', $request->medico_id);
            }

            if ($request->has('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            if ($request->has('fecha_desde')) {
                $query->whereDate('fecha_atencion', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('fecha_atencion', '<=', $request->fecha_hasta);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('paciente', function($q) use ($search) {
                    $q->where('nombre_completo', 'like', "%{$search}%")
                      ->orWhere('numero_identificacion', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $query->orderBy('fecha_atencion', 'desc');

            // Paginación
            $perPage = $request->get('per_page', 15);
            $historias = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $historias
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historias clínicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva historia clínica
     */
    public function store(Request $request)
    {
        // ✅ VALIDACIÓN CORREGIDA
        $request->validate([
            'paciente_uuid' => 'required|string',
            'usuario_id' => 'required|integer',
            'sede_id' => 'required|integer',
            'cita_uuid' => 'required|string',
            'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL,URGENCIAS',
            'motivo_consulta' => 'required|string',
            'enfermedad_actual' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            // Generar número de historia
            $ultimaHistoria = HistoriaClinica::whereYear('created_at', date('Y'))
                ->orderBy('numero_historia', 'desc')
                ->first();
            
            $numeroHistoria = $ultimaHistoria 
                ? $ultimaHistoria->numero_historia + 1 
                : date('Y') . '0001';

            // ✅ CREACIÓN CORREGIDA
            $historia = HistoriaClinica::create([
                'uuid' => Str::uuid(),
                'numero_historia' => $numeroHistoria,
                'cita_id' => $this->getCitaIdFromUuid($request->cita_uuid),
                'sede_id' => $request->sede_id,
                'fecha_atencion' => now(),
                'tipo_consulta' => $request->tipo_consulta,
                'motivo_consulta' => $request->motivo_consulta,
                'enfermedad_actual' => $request->enfermedad_actual,
                
                // Signos vitales
                'peso' => $request->peso,
                'talla' => $request->talla,
                'imc' => $request->imc,
                'temperatura' => $request->temperatura,
                'presion_arterial' => $request->presion_arterial,
                'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
                'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
                'saturacion_oxigeno' => $request->saturacion_oxigeno,
                'perimetro_abdominal' => $request->perimetro_abdominal,
                
                // Antecedentes familiares
                'af_hta_padre' => $request->af_hta_padre ?? 'NO',
                'af_hta_madre' => $request->af_hta_madre ?? 'NO',
                'af_hta_hermanos' => $request->af_hta_hermanos ?? 'NO',
                'af_hta_abuelos' => $request->af_hta_abuelos ?? 'NO',
                'af_hta_otros' => $request->af_hta_otros ?? 'NO',
                'af_dm_padre' => $request->af_dm_padre ?? 'NO',
                'af_dm_madre' => $request->af_dm_madre ?? 'NO',
                'af_dm_hermanos' => $request->af_dm_hermanos ?? 'NO',
                'af_dm_abuelos' => $request->af_dm_abuelos ?? 'NO',
                'af_dm_otros' => $request->af_dm_otros ?? 'NO',
                // ... más antecedentes familiares
                'obs_antecedentes_familiares' => $request->obs_antecedentes_familiares ?? 'NO REFIERE',
                
                // Antecedentes personales
                'ap_hta' => $request->ap_hta ?? 'NO',
                'obs_ap_hta' => $request->obs_ap_hta,
                'ap_dm' => $request->ap_dm ?? 'NO',
                'obs_ap_dm' => $request->obs_ap_dm,
                'ap_erc' => $request->ap_erc ?? 'NO',
                'obs_ap_erc' => $request->obs_ap_erc,
                // ... más antecedentes personales
                'ap_tabaquismo' => $request->ap_tabaquismo ?? 'NO',
                'obs_ap_tabaquismo' => $request->obs_ap_tabaquismo,
                'ap_alcoholismo' => $request->ap_alcoholismo ?? 'NO',
                'obs_ap_alcoholismo' => $request->obs_ap_alcoholismo,
                'ap_actividad_fisica' => $request->ap_actividad_fisica ?? 'SEDENTARIO',
                'obs_ap_actividad_fisica' => $request->obs_ap_actividad_fisica,
                'ap_quirurgicos' => $request->ap_quirurgicos ?? 'NO REFIERE',
                'ap_traumaticos' => $request->ap_traumaticos ?? 'NO REFIERE',
                'ap_alergicos' => $request->ap_alergicos ?? 'NO REFIERE',
                'obs_antecedentes_personales' => $request->obs_antecedentes_personales ?? 'NO REFIERE',
                
                // Revisión por sistemas
                'rs_general' => $request->rs_general ?? 'NORMAL',
                'obs_rs_general' => $request->obs_rs_general,
                'rs_piel_faneras' => $request->rs_piel_faneras ?? 'NORMAL',
                'obs_rs_piel_faneras' => $request->obs_rs_piel_faneras,
                // ... más sistemas
                'obs_revision_sistemas' => $request->obs_revision_sistemas ?? 'NO REFIERE',
                
                // Examen físico
                'examen_fisico_general' => $request->examen_fisico_general,
                'examen_fisico_cabeza' => $request->examen_fisico_cabeza,
                'examen_fisico_cuello' => $request->examen_fisico_cuello,
                'examen_fisico_torax' => $request->examen_fisico_torax,
                'examen_fisico_abdomen' => $request->examen_fisico_abdomen,
                'examen_fisico_extremidades' => $request->examen_fisico_extremidades,
                'examen_fisico_neurologico' => $request->examen_fisico_neurologico,
                'obs_examen_fisico' => $request->obs_examen_fisico,
                
                // Clasificación
                'clasificacion_hta' => $request->clasificacion_hta,
                'clasificacion_dm' => $request->clasificacion_dm,
                'clasificacion_erc_estado' => $request->clasificacion_erc_estado,
                'clasificacion_erc_categoria_ambulatoria_persistente' => $request->clasificacion_erc_categoria_ambulatoria_persistente,
                'clasificacion_rcv' => $request->clasificacion_rcv,
                'obs_clasificacion' => $request->obs_clasificacion,
                
                // Plan de manejo
                'analisis_plan' => $request->analisis_plan,
                'recomendaciones' => $request->recomendaciones,
                'proximo_control' => $request->proximo_control,
                'tipo_control' => $request->tipo_control,
                'observaciones_plan' => $request->observaciones_plan,
                
                // Educación
                'edu_medicamentos' => $request->edu_medicamentos ?? 'NO',
                'edu_dieta' => $request->edu_dieta ?? 'NO',
                'edu_ejercicio' => $request->edu_ejercicio ?? 'NO',
                'edu_signos_alarma' => $request->edu_signos_alarma ?? 'NO',
                'observaciones_educacion' => $request->observaciones_educacion,
                
                // Egreso
                'estado_egreso' => $request->estado_egreso ?? 'VIVO',
                'destino_egreso' => $request->destino_egreso ?? 'DOMICILIO',
                'condicion_egreso' => $request->condicion_egreso ?? 'MEJORADO',
                'observaciones_egreso' => $request->observaciones_egreso,
                
                'estado' => 'ACTIVA',
                'created_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Historia clínica creada exitosamente',
                'data' => $historia->load(['paciente', 'medico', 'sede'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar historia clínica específica
     */
    public function show($uuid)
    {
        try {
            $historia = HistoriaClinica::with([
                'paciente',
                'medico',
                'sede',
                'diagnosticos.diagnostico',
                'medicamentos.medicamento',
                'cups.cups',
                'remisiones.remision',
                'incapacidades.diagnostico',
                'examenesPdf'
            ])->where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $historia
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Historia clínica no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar historia clínica
     */
    public function update(Request $request, $uuid)
    {
        $request->validate([
            'motivo_consulta' => 'required|string',
            'enfermedad_actual' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $historia->update([
                'motivo_consulta' => $request->motivo_consulta,
                'enfermedad_actual' => $request->enfermedad_actual,
                
                // Signos vitales
                'peso' => $request->peso,
                'talla' => $request->talla,
                'imc' => $request->imc,
                'temperatura' => $request->temperatura,
                'presion_arterial' => $request->presion_arterial,
                'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
                'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
                'saturacion_oxigeno' => $request->saturacion_oxigeno,
                'perimetro_abdominal' => $request->perimetro_abdominal,
                
                // Antecedentes (todos los campos según el request)
                // ... (similar al store)
                
                // Plan de manejo
                'analisis_plan' => $request->analisis_plan,
                'recomendaciones' => $request->recomendaciones,
                'proximo_control' => $request->proximo_control,
                'tipo_control' => $request->tipo_control,
                
                'updated_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Historia clínica actualizada exitosamente',
                'data' => $historia->fresh()->load(['paciente', 'medico', 'sede'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar historia clínica (soft delete)
     */
    public function destroy($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $historia->delete();

            return response()->json([
                'success' => true,
                'message' => 'Historia clínica eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener diagnósticos de la historia
     */
    public function getDiagnosticos($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $diagnosticos = $historia->diagnosticos()->with('diagnostico')->get();

            return response()->json([
                'success' => true,
                'data' => $diagnosticos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener diagnósticos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar diagnóstico a la historia
     */
    public function addDiagnostico(Request $request, $uuid)
    {
        $request->validate([
            'diagnostico_id' => 'required|exists:diagnosticos,id',
            'tipo' => 'required|in:PRINCIPAL,RELACIONADO,COMPLICACIÓN'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $diagnosticoHistoria = $historia->diagnosticos()->create([
                'uuid' => Str::uuid(),
                'diagnostico_id' => $request->diagnostico_id,
                'tipo' => $request->tipo,
                'observaciones' => $request->observaciones,
                'estado' => 'ACTIVO'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico agregado exitosamente',
                'data' => $diagnosticoHistoria->load('diagnostico')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar diagnóstico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar diagnóstico de la historia
     */
    public function removeDiagnostico($uuid, $diagnosticoUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $diagnostico = $historia->diagnosticos()->where('uuid', $diagnosticoUuid)->firstOrFail();
            $diagnostico->delete();

            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar diagnóstico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

       /**
     * Obtener medicamentos de la historia
     */
    public function getMedicamentos($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $medicamentos = $historia->medicamentos()->with('medicamento')->get();

            return response()->json([
                'success' => true,
                'data' => $medicamentos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener medicamentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar medicamento a la historia
     */
    public function addMedicamento(Request $request, $uuid)
    {
        $request->validate([
            'medicamento_id' => 'required|exists:medicamentos,id',
            'via' => 'required|string',
            'dosis' => 'required|string',
            'frecuencia' => 'required|string',
            'duracion' => 'required|string'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $medicamento = Medicamento::findOrFail($request->medicamento_id);

            $medicamentoHistoria = $historia->medicamentos()->create([
                'uuid' => Str::uuid(),
                'medicamento_id' => $request->medicamento_id,
                'concentracion' => $medicamento->concentracion,
                'via' => $request->via,
                'dosis' => $request->dosis,
                'frecuencia' => $request->frecuencia,
                'duracion' => $request->duracion,
                'indicaciones' => $request->indicaciones,
                'estado' => 'ACTIVO'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Medicamento agregado exitosamente',
                'data' => $medicamentoHistoria->load('medicamento')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar medicamento de la historia
     */
    public function removeMedicamento($uuid, $medicamentoUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $medicamento = $historia->medicamentos()->where('uuid', $medicamentoUuid)->firstOrFail();
            $medicamento->delete();

            return response()->json([
                'success' => true,
                'message' => 'Medicamento eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener CUPS de la historia
     */
    public function getCups($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $cups = $historia->cups()->with('cups')->get();

            return response()->json([
                'success' => true,
                'data' => $cups
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener CUPS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar CUPS a la historia
     */
    public function addCups(Request $request, $uuid)
    {
        $request->validate([
            'cups_id' => 'required',
            'cantidad' => 'required|integer|min:1'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $cupsHistoria = $historia->cups()->create([
                'uuid' => Str::uuid(),
                'cups_id' => $request->cups_id,
                'observacion' => $request->observacion,
                'cantidad' => $request->cantidad,
                'estado' => 'PENDIENTE'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'CUPS agregado exitosamente',
                'data' => $cupsHistoria->load('cups')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar CUPS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar CUPS de la historia
     */
    public function removeCups($uuid, $cupsUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $cups = $historia->cups()->where('uuid', $cupsUuid)->firstOrFail();
            $cups->delete();

            return response()->json([
                'success' => true,
                'message' => 'CUPS eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar CUPS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener remisiones de la historia
     */
    public function getRemisiones($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $remisiones = $historia->remisiones()->with('remision')->get();

            return response()->json([
                'success' => true,
                'data' => $remisiones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener remisiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar remisión a la historia
     */
    public function addRemision(Request $request, $uuid)
    {
        $request->validate([
            'remision_id' => 'required|exists:remisiones,id',
            'prioridad' => 'required|in:ALTA,MEDIA,BAJA'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $remisionHistoria = $historia->remisiones()->create([
                'uuid' => Str::uuid(),
                'remision_id' => $request->remision_id,
                'observacion' => $request->observacion,
                'prioridad' => $request->prioridad,
                'estado' => 'PENDIENTE'
            ]);

            // ✅ ERROR CORREGIDO: return response() con espacio
            return response()->json([
                'success' => true,
                'message' => 'Remisión agregada exitosamente',
                'data' => $remisionHistoria->load('remision')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar remisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar remisión de la historia
     */
    public function removeRemision($uuid, $remisionUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $remision = $historia->remisiones()->where('uuid', $remisionUuid)->firstOrFail();
            $remision->delete();

            return response()->json([
                'success' => true,
                'message' => 'Remisión eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar remisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener incapacidades de la historia
     */
    public function getIncapacidades($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $incapacidades = $historia->incapacidades()->with('diagnostico')->get();

            return response()->json([
                'success' => true,
                'data' => $incapacidades
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener incapacidades',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar incapacidad a la historia
     */
    public function addIncapacidad(Request $request, $uuid)
    {
        $request->validate([
            'tipo' => 'required|in:ENFERMEDAD GENERAL,ACCIDENTE DE TRABAJO,ENFERMEDAD LABORAL,LICENCIA DE MATERNIDAD,LICENCIA DE PATERNIDAD',
            'fecha_inicio' => 'required|date',
            'dias' => 'required|integer|min:1',
            'diagnostico_id' => 'required|exists:diagnosticos,id'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            // Calcular fecha fin
            $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);
            $fechaFin = $fechaInicio->copy()->addDays($request->dias);

            $incapacidad = $historia->incapacidades()->create([
                'uuid' => Str::uuid(),
                'tipo' => $request->tipo,
                'fecha_inicio' => $request->fecha_inicio,
                'dias' => $request->dias,
                'fecha_fin' => $fechaFin,
                'diagnostico_id' => $request->diagnostico_id,
                'observaciones' => $request->observaciones,
                'estado' => 'ACTIVA'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Incapacidad agregada exitosamente',
                'data' => $incapacidad->load('diagnostico')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar incapacidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar incapacidad de la historia
     */
    public function removeIncapacidad($uuid, $incapacidadUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $incapacidad = $historia->incapacidades()->where('uuid', $incapacidadUuid)->firstOrFail();
            $incapacidad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Incapacidad eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar incapacidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener exámenes PDF de la historia
     */
    public function getExamenesPDF($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $examenes = $historia->examenesPdf;

            return response()->json([
                'success' => true,
                'data' => $examenes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener exámenes PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

       /**
     * Agregar examen PDF a la historia
     */
    public function addExamenPDF(Request $request, $uuid)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf|max:10240', // 10MB máximo
            'observacion' => 'required|string'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            // Guardar archivo
            $archivo = $request->file('archivo');
            $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
            $rutaArchivo = $archivo->storeAs('examenes_pdf', $nombreArchivo, 'public');

            $examenPdf = $historia->examenesPdf()->create([
                'uuid' => Str::uuid(),
                'nombre_archivo' => $nombreArchivo,
                'ruta_archivo' => $rutaArchivo,
                'url_archivo' => Storage::url($rutaArchivo),
                'observacion' => $request->observacion,
                'uploaded_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Examen PDF agregado exitosamente',
                'data' => $examenPdf
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar examen PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar examen PDF de la historia
     */
    public function removeExamenPDF($uuid, $examenUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $examen = $historia->examenesPdf()->where('uuid', $examenUuid)->firstOrFail();

            // Eliminar archivo físico
            if (Storage::disk('public')->exists($examen->ruta_archivo)) {
                Storage::disk('public')->delete($examen->ruta_archivo);
            }

            $examen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Examen PDF eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar examen PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial completo de un paciente
     */
    public function historialPaciente($pacienteId)
    {
        try {
            $paciente = Paciente::findOrFail($pacienteId);
            
            $historias = HistoriaClinica::with([
                'medico',
                'sede',
                'diagnosticos.diagnostico',
                'medicamentos.medicamento',
                'incapacidades.diagnostico'
            ])
            ->where('paciente_id', $pacienteId)
            ->orderBy('fecha_atencion', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'paciente' => $paciente,
                    'historias' => $historias
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial del paciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de la historia clínica completa
     */
    public function generarPDF($uuid)
    {
        try {
            $historia = HistoriaClinica::with([
                'paciente',
                'medico',
                'sede',
                'diagnosticos.diagnostico',
                'medicamentos.medicamento',
                'cups.cups',
                'remisiones.remision',
                'incapacidades.diagnostico'
            ])->where('uuid', $uuid)->firstOrFail();

            $pdf = PDF::loadView('pdf.historia-clinica', compact('historia'));
            
            return $pdf->download("historia_clinica_{$historia->numero_historia}.pdf");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de receta médica
     */
    public function generarRecetaPDF($uuid)
    {
        try {
            $historia = HistoriaClinica::with([
                'paciente',
                'medico',
                'sede',
                'medicamentos.medicamento'
            ])->where('uuid', $uuid)->firstOrFail();

            if ($historia->medicamentos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay medicamentos para generar receta'
                ], 400);
            }

            $pdf = PDF::loadView('pdf.receta-medica', compact('historia'));
            
            return $pdf->download("receta_{$historia->numero_historia}.pdf");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar receta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de incapacidad
     */
    public function generarIncapacidadPDF($uuid, $incapacidadUuid)
    {
        try {
            $historia = HistoriaClinica::with(['paciente', 'medico', 'sede'])
                ->where('uuid', $uuid)
                ->firstOrFail();

            $incapacidad = $historia->incapacidades()
                ->with('diagnostico')
                ->where('uuid', $incapacidadUuid)
                ->firstOrFail();

            $pdf = PDF::loadView('pdf.incapacidad', compact('historia', 'incapacidad'));
            
            return $pdf->download("incapacidad_{$incapacidad->uuid}.pdf");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar incapacidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO HELPER AGREGADO - Obtener ID de cita desde UUID
     */
    private function getCitaIdFromUuid($citaUuid)
    {
        $cita = \App\Models\Cita::where('uuid', $citaUuid)->first();
        return $cita ? $cita->id : null;
    }
}
