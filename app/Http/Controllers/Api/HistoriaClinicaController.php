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
use Illuminate\Support\Facades\Log;
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
                'historiaDiagnosticos.diagnostico',    
                'historiaMedicamentos.medicamento',   
                'historiaRemisiones.remision',         
                'historiaCups.cups'            
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



public function store(Request $request)
{
    // ✅ VALIDACIÓN
    $request->validate([
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'cita_uuid' => 'required|string',
        'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL,URGENCIAS',
        'motivo_consulta' => 'nullable|string',
        'enfermedad_actual' => 'nullable|string',
        'idDiagnostico' => 'nullable|string',
        'diagnosticos' => 'nullable|array',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        'medicamentos' => 'nullable|array',
        'medicamentos.*.medicamento_id' => 'required_with:medicamentos|string',
        'remisiones' => 'nullable|array',
        'remisiones.*.remision_id' => 'required_with:remisiones|string',
        'cups' => 'nullable|array',
        'cups.*.cups_id' => 'required_with:cups|string',
    ]);

    DB::beginTransaction();
    try {
        // ✅ OBTENER CITA
        $cita = \App\Models\Cita::where('uuid', $request->cita_uuid)->first();
        if (!$cita) {
            throw new \Exception('Cita no encontrada con UUID: ' . $request->cita_uuid);
        }

        // ✅ DETECTAR ESPECIALIDAD
        $especialidad = $cita->agenda->usuarioMedico->especialidad->nombre ?? 'MEDICINA GENERAL';
        
        \Log::info('🔍 Especialidad detectada en store', [
            'especialidad' => $especialidad,
            'tipo_consulta' => $request->tipo_consulta,
            'cita_uuid' => $request->cita_uuid
        ]);

        // ✅ SI ES FISIOTERAPIA, USAR MÉTODO ESPECÍFICO
        if ($especialidad === 'FISIOTERAPIA') {
            DB::rollBack();
            return $this->storeFisioterapia($request, $cita);
        }

        
        if ($especialidad === 'PSICOLOGÍA' || $especialidad === 'PSICOLOGIA') {
            DB::rollBack();
            return $this->storePsicologia($request, $cita);
        }

        if ($especialidad === 'NUTRICIÓN' || $especialidad === 'NUTRICION' || $especialidad === 'NUTRICIONISTA') {
            DB::rollBack();
            return $this->storeNutricionista($request, $cita);
        }
        
        // ✅ PREPARAR DATOS SEGÚN TIPO DE CONSULTA
        $datosHistoria = $this->prepararDatosHistoriaSegunTipo($request, $cita);

        // ✅ CREAR HISTORIA
        $historia = HistoriaClinica::create($datosHistoria);

        // ✅ PROCESAR DIAGNÓSTICOS (sin cambios)
        $this->procesarDiagnosticos($request, $historia);

        // ✅ PROCESAR MEDICAMENTOS (sin cambios)
        $this->procesarMedicamentos($request, $historia);

        // ✅ PROCESAR REMISIONES (sin cambios)
        $this->procesarRemisiones($request, $historia);

        // ✅ PROCESAR CUPS (sin cambios)
        $this->procesarCups($request, $historia);

        DB::commit();

        // ✅ CARGAR RELACIONES
        $historia->load([
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups'
        ]);

        \Log::info('✅ Historia clínica creada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Historia clínica creada exitosamente',
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error creando historia clínica', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * ✅ PREPARAR DATOS SEGÚN TIPO DE CONSULTA
 */
private function prepararDatosHistoriaSegunTipo(Request $request, $cita)
{
    // ✅ DATOS BASE (SIEMPRE SE GUARDAN)
    $datos = [
        'uuid' => $request->uuid ?? Str::uuid(),
        'sede_id' => $request->sede_id,
        'cita_id' => $cita->id,
        'motivo_consulta' => $request->motivo_consulta ?? '',
        'enfermedad_actual' => $request->enfermedad_actual ?? '',
    ];

    // ✅ CAMPOS COMUNES A PRIMERA VEZ Y CONTROL (CON VALORES POR DEFECTO)
    $camposComunes = [
        'finalidad' => $request->finalidad ?? 'CONSULTA',
        'causa_externa' => $request->causa_externa,
        'acompanante' => $request->acompanante,
        'acu_parentesco' => $request->acu_parentesco,
        'acu_telefono' => $request->acu_telefono,
        
        // Medidas antropométricas
        'peso' => $request->peso,
        'talla' => $request->talla,
        'imc' => $request->imc,
        'clasificacion' => $request->clasificacion,
        'perimetro_abdominal' => $request->perimetro_abdominal,
        'obs_perimetro_abdominal' => $request->obs_perimetro_abdominal,
        
        // Test de Morisky
        'olvida_tomar_medicamentos' => $request->olvida_tomar_medicamentos ?? 'NO',
        'toma_medicamentos_hora_indicada' => $request->toma_medicamentos_hora_indicada ?? 'SI',
        'cuando_esta_bien_deja_tomar_medicamentos' => $request->cuando_esta_bien_deja_tomar_medicamentos ?? 'NO',
        'siente_mal_deja_tomarlos' => $request->siente_mal_deja_tomarlos ?? 'NO',
        'valoracion_psicologia' => $request->valoracion_psicologia ?? 'NO',
        'adherente' => $request->adherente,
        
        // Revisión por sistemas
        'general' => $request->general,
        'cabeza' => $request->cabeza ?? 'NORMAL',
        'respiratorio' => $request->respiratorio,
        'cardiovascular' => $request->cardiovascular ?? 'NORMAL',
        'gastrointestinal' => $request->gastrointestinal ?? 'NORMAL',
        'osteoatromuscular' => $request->osteoatromuscular ?? 'NORMAL',
        'snc' => $request->snc ?? 'NORMAL',
        
        // Signos vitales
        'presion_arterial_sistolica_sentado_pie' => $request->presion_arterial_sistolica_sentado_pie,
        'presion_arterial_distolica_sentado_pie' => $request->presion_arterial_distolica_sentado_pie,
        'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
        'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
        
        // Examen físico
        'ef_cabeza' => $request->ef_cabeza ?? 'NORMAL',
        'agudeza_visual' => $request->agudeza_visual ?? 'NORMAL',
        'oidos' => $request->oidos ?? 'NORMAL',
        'nariz_senos_paranasales' => $request->nariz_senos_paranasales ?? 'NORMAL',
        'cavidad_oral' => $request->cavidad_oral ?? 'NORMAL',
        'cuello' => $request->cuello ?? 'NORMAL',
        'cardio_respiratorio' => $request->cardio_respiratorio ?? 'NORMAL',
        'mamas' => $request->mamas ?? 'NORMAL',
        'genito_urinario' => $request->genito_urinario ?? 'NORMAL',
        'musculo_esqueletico' => $request->musculo_esqueletico ?? 'NORMAL',
        'piel_anexos_pulsos' => $request->piel_anexos_pulsos ?? 'NORMAL',
        'inspeccion_sensibilidad_pies' => $request->inspeccion_sensibilidad_pies ?? 'NORMAL',
        'sistema_nervioso' => $request->sistema_nervioso ?? 'NORMAL',
        'capacidad_cognitiva_orientacion' => $request->capacidad_cognitiva_orientacion ?? 'NORMAL',
        'reflejo_aquiliar' => $request->reflejo_aquiliar ?? 'NORMAL',
        'reflejo_patelar' => $request->reflejo_patelar ?? 'NORMAL',
        
        // Observaciones examen físico
        'obs_cabeza' => $request->obs_cabeza,
        'obs_agudeza_visual' => $request->obs_agudeza_visual,
        'obs_cuello' => $request->obs_cuello,
        'obs_torax' => $request->obs_torax,
        'obs_mamas' => $request->obs_mamas,
        'obs_abdomen' => $request->obs_abdomen,
        'obs_genito_urinario' => $request->obs_genito_urinario,
        'obs_extremidades' => $request->obs_extremidades,
        'obs_piel_anexos_pulsos' => $request->obs_piel_anexos_pulsos,
        'obs_sistema_nervioso' => $request->obs_sistema_nervioso,
        'obs_orientacion' => $request->obs_orientacion,
        'hallazgo_positivo_examen_fisico' => $request->hallazgo_positivo_examen_fisico,
        
        // Factores de riesgo
        'dislipidemia' => $request->dislipidemia ?? 'NO',
        'lesion_organo_blanco' => $request->lesion_organo_blanco ?? 'NO',
        'descripcion_lesion_organo_blanco' => $request->descripcion_lesion_organo_blanco,
        
        // Exámenes complementarios
        'fex_es' => $request->fex_es,
        'electrocardiograma' => $request->electrocardiograma,
        'fex_es1' => $request->fex_es1,
        'ecocardiograma' => $request->ecocardiograma,
        'fex_es2' => $request->fex_es2,
        'ecografia_renal' => $request->ecografia_renal,
        
        // Clasificaciones
        'clasificacion_estado_metabolico' => $request->clasificacion_estado_metabolico,
        'clasificacion_hta' => $request->clasificacion_hta,
        'clasificacion_dm' => $request->clasificacion_dm,
        'clasificacion_rcv' => $request->clasificacion_rcv,
        'clasificacion_erc_estado' => $request->clasificacion_erc_estado,
        'clasificacion_erc_categoria_ambulatoria_persistente' => $request->clasificacion_erc_categoria_ambulatoria_persistente,
        
        // Tasas de filtración
        'tasa_filtracion_glomerular_ckd_epi' => $request->tasa_filtracion_glomerular_ckd_epi,
        'tasa_filtracion_glomerular_gockcroft_gault' => $request->tasa_filtracion_glomerular_gockcroft_gault,
        
        // Antecedentes personales
        'hipertension_arterial_personal' => $request->hipertension_arterial_personal ?? 'NO',
        'obs_personal_hipertension_arterial' => $request->obs_personal_hipertension_arterial,
        'diabetes_mellitus_personal' => $request->diabetes_mellitus_personal ?? 'NO',
        'obs_personal_mellitus' => $request->obs_personal_mellitus,
        
        // Educación en salud
        'alimentacion' => $request->alimentacion ?? 'NO',
        'disminucion_consumo_sal_azucar' => $request->disminucion_consumo_sal_azucar ?? 'NO',
        'fomento_actividad_fisica' => $request->fomento_actividad_fisica ?? 'NO',
        'importancia_adherencia_tratamiento' => $request->importancia_adherencia_tratamiento ?? 'NO',
        'consumo_frutas_verduras' => $request->consumo_frutas_verduras ?? 'NO',
        'manejo_estres' => $request->manejo_estres ?? 'NO',
        'disminucion_consumo_cigarrillo' => $request->disminucion_consumo_cigarrillo ?? 'NO',
        'disminucion_peso' => $request->disminucion_peso ?? 'NO',
        
        'observaciones_generales' => $request->observaciones_generales,
    ];

    // ✅ AGREGAR CAMPOS COMUNES
    $datos = array_merge($datos, $camposComunes);

    // ✅ CAMPOS EXCLUSIVOS DE PRIMERA VEZ (CON VALORES POR DEFECTO)
    if ($request->tipo_consulta === 'PRIMERA VEZ') {
        $camposPrimeraVez = [
            // Discapacidades
            'discapacidad_fisica' => $request->discapacidad_fisica ?? 'NO',
            'discapacidad_visual' => $request->discapacidad_visual ?? 'NO',
            'discapacidad_mental' => $request->discapacidad_mental ?? 'NO',
            'discapacidad_auditiva' => $request->discapacidad_auditiva ?? 'NO',
            'discapacidad_intelectual' => $request->discapacidad_intelectual ?? 'NO',
            
            // Drogodependencia
            'drogo_dependiente' => $request->drogo_dependiente ?? 'NO',
            'drogo_dependiente_cual' => $request->drogo_dependiente_cual,
            
            // Antecedentes Familiares
            'hipertension_arterial' => $request->hipertension_arterial ?? 'NO',
            'parentesco_hipertension' => $request->parentesco_hipertension,
            'diabetes_mellitus' => $request->diabetes_mellitus ?? 'NO',
            'parentesco_mellitus' => $request->parentesco_mellitus,
            'artritis' => $request->artritis ?? 'NO',
            'parentesco_artritis' => $request->parentesco_artritis,
            'enfermedad_cardiovascular' => $request->enfermedad_cardiovascular ?? 'NO',
            'parentesco_cardiovascular' => $request->parentesco_cardiovascular,
            'antecedente_metabolico' => $request->antecedente_metabolico ?? 'NO',
            'parentesco_metabolico' => $request->parentesco_metabolico,
            'cancer_mama_estomago_prostata_colon' => $request->cancer_mama_estomago_prostata_colon ?? 'NO',
            'parentesco_cancer' => $request->parentesco_cancer,
            'leucemia' => $request->leucemia ?? 'NO',
            'parentesco_leucemia' => $request->parentesco_leucemia,
            'vih' => $request->vih ?? 'NO',
            'parentesco_vih' => $request->parentesco_vih,
            'otro' => $request->otro ?? 'NO',
            'parentesco_otro' => $request->parentesco_otro,
            
            // Antecedentes Personales Adicionales
            'enfermedad_cardiovascular_personal' => $request->enfermedad_cardiovascular_personal ?? 'NO',
            'obs_personal_enfermedad_cardiovascular' => $request->obs_personal_enfermedad_cardiovascular,
            'arterial_periferica_personal' => $request->arterial_periferica_personal ?? 'NO',
            'obs_personal_arterial_periferica' => $request->obs_personal_arterial_periferica,
            'carotidea_personal' => $request->carotidea_personal ?? 'NO',
            'obs_personal_carotidea' => $request->obs_personal_carotidea,
            'aneurisma_aorta_personal' => $request->aneurisma_aorta_personal ?? 'NO',
            'obs_personal_aneurisma_aorta' => $request->obs_personal_aneurisma_aorta,
            'sindrome_coronario_agudo_angina_personal' => $request->sindrome_coronario_agudo_angina_personal ?? 'NO',
            'obs_personal_sindrome_coronario' => $request->obs_personal_sindrome_coronario,
            'artritis_personal' => $request->artritis_personal ?? 'NO',
            'obs_personal_artritis' => $request->obs_personal_artritis,
            'iam_personal' => $request->iam_personal ?? 'NO',
            'obs_personal_iam' => $request->obs_personal_iam,
            'revascul_coronaria_personal' => $request->revascul_coronaria_personal ?? 'NO',
            'obs_personal_revascul_coronaria' => $request->obs_personal_revascul_coronaria,
            'insuficiencia_cardiaca_personal' => $request->insuficiencia_cardiaca_personal ?? 'NO',
            'obs_personal_insuficiencia_cardiaca' => $request->obs_personal_insuficiencia_cardiaca,
            'amputacion_pie_diabetico_personal' => $request->amputacion_pie_diabetico_personal ?? 'NO',
            'obs_personal_amputacion_pie_diabetico' => $request->obs_personal_amputacion_pie_diabetico,
            'enfermedad_pulmonar_personal' => $request->enfermedad_pulmonar_personal ?? 'NO',
            'obs_personal_enfermedad_pulmonar' => $request->obs_personal_enfermedad_pulmonar,
            'victima_maltrato_personal' => $request->victima_maltrato_personal ?? 'NO',
            'obs_personal_maltrato_personal' => $request->obs_personal_maltrato_personal,
            'antecedentes_quirurgicos' => $request->antecedentes_quirurgicos ?? 'NO',
            'obs_personal_antecedentes_quirurgicos' => $request->obs_personal_antecedentes_quirurgicos,
            'acontosis_personal' => $request->acontosis_personal ?? 'NO',
            'obs_personal_acontosis' => $request->obs_personal_acontosis,
            'otro_personal' => $request->otro_personal ?? 'NO',
            'obs_personal_otro' => $request->obs_personal_otro,
            
            // Revisión por sistemas adicional
            'orl' => $request->orl ?? 'NORMAL',
            'revision_sistemas' => $request->revision_sistemas,
            
            // Examen físico adicional
            'presion_arterial_sistolica_acostado' => $request->presion_arterial_sistolica_acostado,
            'presion_arterial_distolica_acostado' => $request->presion_arterial_distolica_acostado,
            'fundoscopia' => $request->fundoscopia ?? 'NORMAL',
            'obs_fundoscopia' => $request->obs_fundoscopia,
            'torax' => $request->torax ?? 'NORMAL',
            'abdomen' => $request->abdomen ?? 'NORMAL',
            'extremidades' => $request->extremidades ?? 'NORMAL',
            'capacidad_cognitiva' => $request->capacidad_cognitiva ?? 'NORMAL',
            'obs_capacidad_cognitiva' => $request->obs_capacidad_cognitiva,
            'orientacion' => $request->orientacion ?? 'NORMAL',
            'obs_reflejo_aquiliar' => $request->obs_reflejo_aquiliar,
            'obs_reflejo_patelar' => $request->obs_reflejo_patelar,
            
            // Factores de riesgo adicionales
            'tabaquismo' => $request->tabaquismo ?? 'NO',
            'obs_tabaquismo' => $request->obs_tabaquismo,
            'obs_dislipidemia' => $request->obs_dislipidemia,
            'menor_cierta_edad' => $request->menor_cierta_edad ?? 'NO',
            'obs_menor_cierta_edad' => $request->obs_menor_cierta_edad,
            'condicion_clinica_asociada' => $request->condicion_clinica_asociada ?? 'NO',
            'obs_condicion_clinica_asociada' => $request->obs_condicion_clinica_asociada,
            'obs_lesion_organo_blanco' => $request->obs_lesion_organo_blanco,
            
            // Otros campos de primera vez
            'insulina_requiriente' => $request->insulina_requiriente,
            'recibe_tratamiento_alternativo' => $request->recibe_tratamiento_alternativo ?? 'NO',
            'recibe_tratamiento_con_plantas_medicinales' => $request->recibe_tratamiento_con_plantas_medicinales ?? 'NO',
            'recibe_ritual_medicina_tradicional' => $request->recibe_ritual_medicina_tradicional ?? 'NO',
            'numero_frutas_diarias' => $request->numero_frutas_diarias ?? 0,
            'elevado_consumo_grasa_saturada' => $request->elevado_consumo_grasa_saturada ?? 'NO',
            'adiciona_sal_despues_preparar_comida' => $request->adiciona_sal_despues_preparar_comida ?? 'NO',
            
            // Reformulación
            'razon_reformulacion' => $request->razon_reformulacion,
            'motivo_reformulacion' => $request->motivo_reformulacion,
            'reformulacion_quien_reclama' => $request->reformulacion_quien_reclama,
            'reformulacion_nombre_reclama' => $request->reformulacion_nombre_reclama,
            'adicional' => $request->adicional,
        ];
        
        $datos = array_merge($datos, $camposPrimeraVez);
    }

    return $datos;
}

/**
 * ✅ PROCESAR DIAGNÓSTICOS
 */
private function procesarDiagnosticos(Request $request, HistoriaClinica $historia)
{
    $diagnosticosProcesados = [];
    
    // Diagnóstico individual
    if ($request->idDiagnostico && !empty($request->idDiagnostico)) {
        $diagnostico = \App\Models\Diagnostico::where('uuid', $request->idDiagnostico)
            ->orWhere('id', $request->idDiagnostico)
            ->first();
        
        if ($diagnostico) {
            \App\Models\HistoriaDiagnostico::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                'diagnostico_id' => $diagnostico->id,
                'tipo' => 'PRINCIPAL',
                'tipo_diagnostico' => $request->tipo_diagnostico ?? 'CONFIRMADO_NUEVO',
            ]);
            $diagnosticosProcesados[] = $diagnostico->id;
        }
    }
    
    // Array de diagnósticos
    if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
        foreach ($request->diagnosticos as $index => $diag) {
            if (!empty($diag['diagnostico_id'])) {
                $diagnostico = \App\Models\Diagnostico::where('uuid', $diag['diagnostico_id'])
                    ->orWhere('id', $diag['diagnostico_id'])
                    ->first();
                
                if ($diagnostico && !in_array($diagnostico->id, $diagnosticosProcesados)) {
                    \App\Models\HistoriaDiagnostico::create([
                        'uuid' => Str::uuid(),
                        'historia_clinica_id' => $historia->id,
                        'diagnostico_id' => $diagnostico->id,
                        'tipo' => $diag['tipo'] ?? ($index === 0 ? 'PRINCIPAL' : 'SECUNDARIO'),
                        'tipo_diagnostico' => $diag['tipo_diagnostico'] ?? 'IMPRESION_DIAGNOSTICA',
                    ]);
                    $diagnosticosProcesados[] = $diagnostico->id;
                }
            }
        }
    }
}

/**
 * ✅ PROCESAR MEDICAMENTOS
 */
private function procesarMedicamentos(Request $request, HistoriaClinica $historia)
{
    if ($request->has('medicamentos') && is_array($request->medicamentos)) {
        foreach ($request->medicamentos as $med) {
            $medicamentoId = $med['medicamento_id'] ?? $med['idMedicamento'] ?? null;
            
            if (!empty($medicamentoId)) {
                $medicamento = \App\Models\Medicamento::where('uuid', $medicamentoId)
                    ->orWhere('id', $medicamentoId)
                    ->first();
                
                if ($medicamento) {
                    \App\Models\HistoriaMedicamento::create([
                        'uuid' => Str::uuid(),
                        'historia_clinica_id' => $historia->id,
                        'medicamento_id' => $medicamento->id,
                        'cantidad' => $med['cantidad'] ?? '1',
                        'dosis' => $med['dosis'] ?? 'Según indicación médica',
                    ]);
                }
            }
        }
    }
}

/**
 * ✅ PROCESAR REMISIONES
 */
private function procesarRemisiones(Request $request, HistoriaClinica $historia)
{
    if ($request->has('remisiones') && is_array($request->remisiones)) {
        foreach ($request->remisiones as $rem) {
            $remisionId = $rem['remision_id'] ?? $rem['idRemision'] ?? null;
            
            if (!empty($remisionId)) {
                $remision = \App\Models\Remision::where('uuid', $remisionId)
                    ->orWhere('id', $remisionId)
                    ->first();
                
                if ($remision) {
                    \App\Models\HistoriaRemision::create([
                        'uuid' => Str::uuid(),
                        'historia_clinica_id' => $historia->id,
                        'remision_id' => $remision->id,
                        'observacion' => $rem['observacion'] ?? $rem['remObservacion'] ?? null,
                    ]);
                }
            }
        }
    }
}

/**
 * ✅ PROCESAR CUPS
 */
private function procesarCups(Request $request, HistoriaClinica $historia)
{
    if ($request->has('cups') && is_array($request->cups)) {
        foreach ($request->cups as $cup) {
            $cupsId = $cup['cups_id'] ?? $cup['idCups'] ?? null;
            
            if (!empty($cupsId)) {
                $cupsModel = \App\Models\Cups::where('uuid', $cupsId)
                    ->orWhere('id', $cupsId)
                    ->first();
                
                if ($cupsModel) {
                    \App\Models\HistoriaCups::create([
                        'uuid' => Str::uuid(),
                        'historia_clinica_id' => $historia->id,
                        'cups_id' => $cupsModel->id,
                        'observacion' => $cup['observacion'] ?? $cup['cupObservacion'] ?? null,
                    ]);
                }
            }
        }
    }
}

private function storeFisioterapia(Request $request, $cita)
{
    // ✅ VALIDACIÓN
    $request->validate([
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL', // ✅ AGREGADO
        'motivo_consulta' => 'nullable|string',
        
        'diagnosticos' => 'required|array|min:1',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        'remisiones' => 'nullable|array',
        'remisiones.*.remision_id' => 'required_with:remisiones|string',
        'remisiones.*.observacion' => 'nullable|string',
        
        'peso' => 'nullable|numeric',
        'talla' => 'nullable|numeric',
        'imc' => 'nullable|numeric',
        'clasificacion' => 'nullable|string',
        'perimetro_abdominal' => 'nullable|numeric',
        'obs_perimetro_abdominal' => 'nullable|string',
        'finalidad' => 'nullable|string',
        'causa_externa' => 'nullable|string',
        'acompanante' => 'nullable|string',
        'acu_parentesco' => 'nullable|string',
        'acu_telefono' => 'nullable|string',
        
        // ✅ CAMPOS DE FISIOTERAPIA (solo para PRIMERA VEZ)
        'actitud' => 'nullable|string',
        'evaluacion_d' => 'nullable|string',
        'evaluacion_p' => 'nullable|string',
        'estado' => 'nullable|string',
        'evaluacion_dolor' => 'nullable|string',
        'evaluacion_os' => 'nullable|string',
        'evaluacion_neu' => 'nullable|string',
        'comitante' => 'nullable|string',
        'plan_seguir' => 'nullable|string',
    ]);

    DB::beginTransaction();
    try {
        \Log::info('🏥 Guardando historia de FISIOTERAPIA', [
            'cita_uuid' => $cita->uuid,
            'tipo_consulta' => $request->tipo_consulta, // ✅ LOG
            'paciente_uuid' => $request->paciente_uuid,
            'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
            'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
        ]);

        // ✅ CREAR HISTORIA BASE (SIEMPRE)
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // Campos básicos
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'acompanante' => $request->acompanante,
            'acu_parentesco' => $request->acu_parentesco,
            'acu_telefono' => $request->acu_telefono,
            'motivo_consulta' => $request->motivo_consulta ?? '',
            
            // Medidas antropométricas
            'peso' => $request->peso,
            'talla' => $request->talla,
            'imc' => $request->imc,
            'clasificacion' => $request->clasificacion,
            'perimetro_abdominal' => $request->perimetro_abdominal,
            'obs_perimetro_abdominal' => $request->obs_perimetro_abdominal,
        ]);

        \Log::info('✅ Historia clínica base creada', [
            'historia_id' => $historia->id,
            'historia_uuid' => $historia->uuid
        ]);

        // ✅✅✅ SOLO CREAR COMPLEMENTARIA SI ES PRIMERA VEZ ✅✅✅
        if ($request->tipo_consulta === 'PRIMERA VEZ') {
            \App\Models\HistoriaClinicaComplementaria::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                
                // Evaluaciones específicas de fisioterapia
                'actitud' => $request->actitud,
                'evaluacion_d' => $request->evaluacion_d,
                'evaluacion_p' => $request->evaluacion_p,
                'estado' => $request->estado,
                'evaluacion_dolor' => $request->evaluacion_dolor,
                'evaluacion_os' => $request->evaluacion_os,
                'evaluacion_neu' => $request->evaluacion_neu,
                'comitante' => $request->comitante,
                'plan_seguir' => $request->plan_seguir,
            ]);

            \Log::info('✅ Tabla complementaria creada (PRIMERA VEZ)');
        } else {
            \Log::info('ℹ️ Tabla complementaria NO creada (CONTROL)');
        }

        // ✅ PROCESAR DIAGNÓSTICOS (IGUAL PARA AMBOS)
        $diagnosticosProcesados = [];
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos FISIOTERAPIA', [
                'count' => count($request->diagnosticos)
            ]);
            
            foreach ($request->diagnosticos as $index => $diag) {
                if (!empty($diag['diagnostico_id'])) {
                    $diagnostico = \App\Models\Diagnostico::where('uuid', $diag['diagnostico_id'])
                        ->orWhere('id', $diag['diagnostico_id'])
                        ->first();
                    
                    if ($diagnostico && !in_array($diagnostico->id, $diagnosticosProcesados)) {
                        \App\Models\HistoriaDiagnostico::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'diagnostico_id' => $diagnostico->id,
                            'tipo' => $diag['tipo'] ?? ($index === 0 ? 'PRINCIPAL' : 'SECUNDARIO'),
                            'tipo_diagnostico' => $diag['tipo_diagnostico'] ?? 'IMPRESION_DIAGNOSTICA',
                        ]);
                        $diagnosticosProcesados[] = $diagnostico->id;
                        \Log::info('✅ Diagnóstico FISIOTERAPIA guardado', [
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES (IGUAL PARA AMBOS)
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones FISIOTERAPIA', [
                'count' => count($request->remisiones)
            ]);
            
            foreach ($request->remisiones as $rem) {
                $remisionId = $rem['remision_id'] ?? null;
                
                if (!empty($remisionId)) {
                    $remision = \App\Models\Remision::where('uuid', $remisionId)
                        ->orWhere('id', $remisionId)
                        ->first();
                    
                    if ($remision) {
                        \App\Models\HistoriaRemision::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'remision_id' => $remision->id,
                            'observacion' => $rem['observacion'] ?? null,
                        ]);
                        \Log::info('✅ Remisión FISIOTERAPIA guardada', [
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    }
                }
            }
        }

        DB::commit();

        // ✅ CARGAR RELACIONES (CONDICIONAL)
        $relaciones = [
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'historiaRemisiones.remision'
        ];

        // Solo cargar complementaria si es PRIMERA VEZ
        if ($request->tipo_consulta === 'PRIMERA VEZ') {
            $relaciones[] = 'complementaria';
        }

        $historia->load($relaciones);

        \Log::info('✅ Historia de fisioterapia guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => $request->tipo_consulta === 'PRIMERA VEZ',
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count()
        ]);

        return response()->json([
            'success' => true,
            'message' => "Historia clínica de fisioterapia ({$request->tipo_consulta}) guardada exitosamente",
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de fisioterapia', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de fisioterapia',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}

private function storePsicologia(Request $request, $cita)
{
    // ✅ VALIDACIÓN DINÁMICA SEGÚN TIPO DE CONSULTA
    $validationRules = [
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL',
        'motivo_consulta' => 'nullable|string',
        
        'diagnosticos' => 'required|array|min:1',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        'finalidad' => 'nullable|string',
        'causa_externa' => 'nullable|string',
        'acompanante' => 'nullable|string',
        'acu_parentesco' => 'nullable|string',
        'acu_telefono' => 'nullable|string',
    ];

    // ✅ AGREGAR VALIDACIONES ESPECÍFICAS SEGÚN TIPO
    if ($request->tipo_consulta === 'PRIMERA VEZ') {
        $validationRules = array_merge($validationRules, [
            'medicamentos' => 'nullable|array',
            'medicamentos.*.medicamento_id' => 'required_with:medicamentos|string',
            'medicamentos.*.cantidad' => 'nullable|string',
            'medicamentos.*.dosis' => 'nullable|string',
            
            'remisiones' => 'nullable|array',
            'remisiones.*.remision_id' => 'required_with:remisiones|string',
            'remisiones.*.observacion' => 'nullable|string',
            
            // Campos complementarios de PRIMERA VEZ
            'estructura_familiar' => 'nullable|string',
            'psicologia_red_apoyo' => 'nullable|string',
            'psicologia_comportamiento_consulta' => 'nullable|string',
            'psicologia_tratamiento_actual_adherencia' => 'nullable|string',
            'psicologia_descripcion_problema' => 'nullable|string',
            'analisis_conclusiones' => 'nullable|string',
            'psicologia_plan_intervencion_recomendacion' => 'nullable|string',
        ]);
    } else { // CONTROL
        $validationRules = array_merge($validationRules, [
            // Campos complementarios de CONTROL (solo 3)
            'psicologia_descripcion_problema' => 'nullable|string',
            'psicologia_plan_intervencion_recomendacion' => 'nullable|string',
            'avance_paciente' => 'nullable|string',
        ]);
    }

    $request->validate($validationRules);

    DB::beginTransaction();
    try {
        \Log::info('🧠 Guardando historia de PSICOLOGÍA', [
            'cita_uuid' => $cita->uuid,
            'tipo_consulta' => $request->tipo_consulta,
            'paciente_uuid' => $request->paciente_uuid,
            'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
            'medicamentos_count' => ($request->tipo_consulta === 'PRIMERA VEZ' && $request->medicamentos) ? count($request->medicamentos) : 0,
            'remisiones_count' => ($request->tipo_consulta === 'PRIMERA VEZ' && $request->remisiones) ? count($request->remisiones) : 0,
        ]);

        // ✅ CREAR HISTORIA BASE (SIEMPRE)
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // Campos básicos
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'acompanante' => $request->acompanante,
            'acu_parentesco' => $request->acu_parentesco,
            'acu_telefono' => $request->acu_telefono,
            'motivo_consulta' => $request->motivo_consulta ?? '',
        ]);

        \Log::info('✅ Historia clínica base creada', [
            'historia_id' => $historia->id,
            'historia_uuid' => $historia->uuid
        ]);

        // ✅ CREAR TABLA COMPLEMENTARIA (AMBOS TIPOS, PERO CON CAMPOS DIFERENTES)
        if ($request->tipo_consulta === 'PRIMERA VEZ') {
            // ✅ PRIMERA VEZ: Todos los campos
            \App\Models\HistoriaClinicaComplementaria::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                
                'estructura_familiar' => $request->estructura_familiar,
                'psicologia_red_apoyo' => $request->psicologia_red_apoyo,
                'psicologia_comportamiento_consulta' => $request->psicologia_comportamiento_consulta,
                'psicologia_tratamiento_actual_adherencia' => $request->psicologia_tratamiento_actual_adherencia,
                'psicologia_descripcion_problema' => $request->psicologia_descripcion_problema,
                'analisis_conclusiones' => $request->analisis_conclusiones,
                'psicologia_plan_intervencion_recomendacion' => $request->psicologia_plan_intervencion_recomendacion,
            ]);

            \Log::info('✅ Tabla complementaria creada (PRIMERA VEZ - 7 campos)');
            
        } else { // CONTROL
            // ✅ CONTROL: Solo 3 campos específicos
            \App\Models\HistoriaClinicaComplementaria::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                
                'psicologia_descripcion_problema' => $request->psicologia_descripcion_problema,
                'psicologia_plan_intervencion_recomendacion' => $request->psicologia_plan_intervencion_recomendacion,
                'avance_paciente' => $request->avance_paciente,
            ]);

            \Log::info('✅ Tabla complementaria creada (CONTROL - 3 campos)');
        }

        // ✅ PROCESAR DIAGNÓSTICOS (AMBOS TIPOS)
        $diagnosticosProcesados = [];
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos PSICOLOGÍA', [
                'count' => count($request->diagnosticos)
            ]);
            
            foreach ($request->diagnosticos as $index => $diag) {
                if (!empty($diag['diagnostico_id'])) {
                    $diagnostico = \App\Models\Diagnostico::where('uuid', $diag['diagnostico_id'])
                        ->orWhere('id', $diag['diagnostico_id'])
                        ->first();
                    
                    if ($diagnostico && !in_array($diagnostico->id, $diagnosticosProcesados)) {
                        \App\Models\HistoriaDiagnostico::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'diagnostico_id' => $diagnostico->id,
                            'tipo' => $diag['tipo'] ?? ($index === 0 ? 'PRINCIPAL' : 'SECUNDARIO'),
                            'tipo_diagnostico' => $diag['tipo_diagnostico'] ?? 'IMPRESION_DIAGNOSTICA',
                        ]);
                        $diagnosticosProcesados[] = $diagnostico->id;
                        \Log::info('✅ Diagnóstico PSICOLOGÍA guardado', [
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR MEDICAMENTOS (SOLO PRIMERA VEZ)
        if ($request->tipo_consulta === 'PRIMERA VEZ' && $request->has('medicamentos') && is_array($request->medicamentos)) {
            \Log::info('🔍 Procesando medicamentos PSICOLOGÍA (PRIMERA VEZ)', [
                'count' => count($request->medicamentos)
            ]);
            
            foreach ($request->medicamentos as $med) {
                $medicamentoId = $med['medicamento_id'] ?? null;
                
                if (!empty($medicamentoId)) {
                    $medicamento = \App\Models\Medicamento::where('uuid', $medicamentoId)
                        ->orWhere('id', $medicamentoId)
                        ->first();
                    
                    if ($medicamento) {
                        \App\Models\HistoriaMedicamento::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'medicamento_id' => $medicamento->id,
                            'cantidad' => $med['cantidad'] ?? '1',
                            'dosis' => $med['dosis'] ?? 'Según indicación médica',
                        ]);
                        \Log::info('✅ Medicamento PSICOLOGÍA guardado', [
                            'medicamento_id' => $medicamento->id,
                            'nombre' => $medicamento->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES (SOLO PRIMERA VEZ)
        if ($request->tipo_consulta === 'PRIMERA VEZ' && $request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones PSICOLOGÍA (PRIMERA VEZ)', [
                'count' => count($request->remisiones)
            ]);
            
            foreach ($request->remisiones as $rem) {
                $remisionId = $rem['remision_id'] ?? null;
                
                if (!empty($remisionId)) {
                    $remision = \App\Models\Remision::where('uuid', $remisionId)
                        ->orWhere('id', $remisionId)
                        ->first();
                    
                    if ($remision) {
                        \App\Models\HistoriaRemision::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'remision_id' => $remision->id,
                            'observacion' => $rem['observacion'] ?? null,
                        ]);
                        \Log::info('✅ Remisión PSICOLOGÍA guardada', [
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    }
                }
            }
        }

        DB::commit();

        // ✅ CARGAR RELACIONES (SIEMPRE INCLUYE COMPLEMENTARIA)
        $relaciones = [
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'complementaria' // ✅ Siempre se carga porque ambos tipos la usan
        ];

        // Solo cargar medicamentos y remisiones si es PRIMERA VEZ
        if ($request->tipo_consulta === 'PRIMERA VEZ') {
            $relaciones[] = 'historiaMedicamentos.medicamento';
            $relaciones[] = 'historiaRemisiones.remision';
        }

        $historia->load($relaciones);

        \Log::info('✅ Historia de psicología guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => true,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $request->tipo_consulta === 'PRIMERA VEZ' ? $historia->historiaMedicamentos->count() : 0,
            'remisiones_count' => $request->tipo_consulta === 'PRIMERA VEZ' ? $historia->historiaRemisiones->count() : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Historia clínica de psicología ({$request->tipo_consulta}) guardada exitosamente",
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de psicología', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de psicología',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}

private function storeNutricionista(Request $request, $cita)
{
    // ✅ VALIDACIÓN DINÁMICA SEGÚN TIPO DE CONSULTA
    $validationRules = [
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL',
        'motivo_consulta' => 'nullable|string',
        
        'diagnosticos' => 'required|array|min:1',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        'medicamentos' => 'nullable|array',
        'medicamentos.*.medicamento_id' => 'required_with:medicamentos|string',
        'medicamentos.*.cantidad' => 'nullable|string',
        'medicamentos.*.dosis' => 'nullable|string',
        
        'remisiones' => 'nullable|array',
        'remisiones.*.remision_id' => 'required_with:remisiones|string',
        'remisiones.*.observacion' => 'nullable|string',
        
        'finalidad' => 'nullable|string',
        'causa_externa' => 'nullable|string',
        'acompanante' => 'nullable|string',
        'acu_parentesco' => 'nullable|string',
        'acu_telefono' => 'nullable|string',
        'tabaquismo' => 'nullable|string',
        
        // Medidas antropométricas (ambos tipos)
        'peso' => 'nullable|numeric',
        'talla' => 'nullable|numeric',
        'imc' => 'nullable|numeric',
        'clasificacion' => 'nullable|string',
        'perimetro_abdominal' => 'nullable|numeric',
    ];

    // ✅ AGREGAR VALIDACIONES ESPECÍFICAS SEGÚN TIPO
    if ($request->tipo_consulta === 'PRIMERA VEZ') {
        $validationRules = array_merge($validationRules, [
            // Antecedentes PRIMERA VEZ
            'enfermedad_diagnostica' => 'nullable|string',
            'habito_intestinal' => 'nullable|string',
            'quirurgicos' => 'nullable|string',
            'quirurgicos_observaciones' => 'nullable|string',
            'alergicos' => 'nullable|string',
            'alergicos_observaciones' => 'nullable|string',
            'familiares' => 'nullable|string',
            'familiares_observaciones' => 'nullable|string',
            'psa' => 'nullable|string',
            'psa_observaciones' => 'nullable|string',
            'farmacologicos' => 'nullable|string',
            'farmacologicos_observaciones' => 'nullable|string',
            'sueno' => 'nullable|string',
            'sueno_observaciones' => 'nullable|string',
            'tabaquismo_observaciones' => 'nullable|string',
            'ejercicio' => 'nullable|string',
            'ejercicio_observaciones' => 'nullable|string',
            
            // Gineco-obstétricos
            'metodo_conceptivo' => 'nullable|string',
            'metodo_conceptivo_cual' => 'nullable|string',
            'embarazo_actual' => 'nullable|string',
            'semanas_gestacion' => 'nullable|integer',
            'climatero' => 'nullable|string',
            
            // Evaluación dietética
            'tolerancia_via_oral' => 'nullable|string',
            'percepcion_apetito' => 'nullable|string',
            'percepcion_apetito_observacion' => 'nullable|string',
            'alimentos_preferidos' => 'nullable|string',
            'alimentos_rechazados' => 'nullable|string',
            'suplemento_nutricionales' => 'nullable|string',
            'dieta_especial' => 'nullable|string',
            'dieta_especial_cual' => 'nullable|string',
            
            // Horarios de comida
            'desayuno_hora' => 'nullable|string',
            'desayuno_hora_observacion' => 'nullable|string',
            'media_manana_hora' => 'nullable|string',
            'media_manana_hora_observacion' => 'nullable|string',
            'almuerzo_hora' => 'nullable|string',
            'almuerzo_hora_observacion' => 'nullable|string',
            'media_tarde_hora' => 'nullable|string',
            'media_tarde_hora_observacion' => 'nullable|string',
            'cena_hora' => 'nullable|string',
            'cena_hora_observacion' => 'nullable|string',
            'refrigerio_nocturno_hora' => 'nullable|string',
            'refrigerio_nocturno_hora_observacion' => 'nullable|string',
            
            // Plan nutricional
            'peso_ideal' => 'nullable|numeric',
            'interpretacion' => 'nullable|string',
            'meta_meses' => 'nullable|integer',
            'analisis_nutricional' => 'nullable|string',
            'plan_seguir' => 'nullable|string',
        ]);
    } else { // CONTROL
        $validationRules = array_merge($validationRules, [
            // Campos CONTROL
            'enfermedad_diagnostica' => 'nullable|string',
            'habito_intestinal' => 'nullable|string',
            
            // Recordatorio 24h
            'comida_desayuno' => 'nullable|string',
            'comida_medio_desayuno' => 'nullable|string',
            'comida_almuerzo' => 'nullable|string',
            'comida_medio_almuerzo' => 'nullable|string',
            'comida_cena' => 'nullable|string',
            
            // Frecuencia de consumo
            'lacteo' => 'nullable|string',
            'lacteo_observacion' => 'nullable|string',
            'huevo' => 'nullable|string',
            'huevo_observacion' => 'nullable|string',
            'embutido' => 'nullable|string',
            'embutido_observacion' => 'nullable|string',
            'carne_roja' => 'nullable|string',
            'carne_blanca' => 'nullable|string',
            'carne_vicera' => 'nullable|string',
            'carne_observacion' => 'nullable|string',
            'leguminosas' => 'nullable|string',
            'leguminosas_observacion' => 'nullable|string',
            'frutas_jugo' => 'nullable|string',
            'frutas_porcion' => 'nullable|string',
            'frutas_observacion' => 'nullable|string',
            'verduras_hortalizas' => 'nullable|string',
            'vh_observacion' => 'nullable|string',
            'cereales' => 'nullable|string',
            'cereales_observacion' => 'nullable|string',
            'rtp' => 'nullable|string',
            'rtp_observacion' => 'nullable|string',
            'azucar_dulce' => 'nullable|string',
            'ad_observacion' => 'nullable|string',
            
            // Plan de seguimiento
            'diagnostico_nutri' => 'nullable|string',
            'plan_seguir_nutri' => 'nullable|string',
            'analisis_nutricional' => 'nullable|string',
        ]);
    }

    $request->validate($validationRules);

    DB::beginTransaction();
    try {
        \Log::info('🥗 Guardando historia de NUTRICIONISTA', [
            'cita_uuid' => $cita->uuid,
            'tipo_consulta' => $request->tipo_consulta,
            'paciente_uuid' => $request->paciente_uuid,
            'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
            'medicamentos_count' => $request->medicamentos ? count($request->medicamentos) : 0,
            'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
        ]);

        // ✅ CREAR HISTORIA BASE (SIEMPRE)
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // Campos básicos
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'acompanante' => $request->acompanante,
            'acu_parentesco' => $request->acu_parentesco,
            'acu_telefono' => $request->acu_telefono,
            'motivo_consulta' => $request->motivo_consulta ?? '',
            
            // Medidas antropométricas (ambos tipos)
            'peso' => $request->peso,
            'talla' => $request->talla,
            'imc' => $request->imc,
            'clasificacion' => $request->clasificacion,
            'perimetro_abdominal' => $request->perimetro_abdominal,
        ]);

        \Log::info('✅ Historia clínica base creada', [
            'historia_id' => $historia->id,
            'historia_uuid' => $historia->uuid
        ]);

        // ✅ CREAR TABLA COMPLEMENTARIA (AMBOS TIPOS, PERO CON CAMPOS DIFERENTES)
        if ($request->tipo_consulta === 'PRIMERA VEZ') {
            // ✅ PRIMERA VEZ: Todos los campos de evaluación inicial
            \App\Models\HistoriaClinicaComplementaria::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                
                // Antecedentes
                'enfermedad_diagnostica' => $request->enfermedad_diagnostica,
                'habito_intestinal' => $request->habito_intestinal,
                'quirurgicos' => $request->quirurgicos,
                'quirurgicos_observaciones' => $request->quirurgicos_observaciones,
                'alergicos' => $request->alergicos,
                'alergicos_observaciones' => $request->alergicos_observaciones,
                'familiares' => $request->familiares,
                'familiares_observaciones' => $request->familiares_observaciones,
                'psa' => $request->psa,
                'psa_observaciones' => $request->psa_observaciones,
                'farmacologicos' => $request->farmacologicos,
                'farmacologicos_observaciones' => $request->farmacologicos_observaciones,
                'sueno' => $request->sueno,
                'sueno_observaciones' => $request->sueno_observaciones,
                'tabaquismo_observaciones' => $request->tabaquismo_observaciones,
                'ejercicio' => $request->ejercicio,
                'ejercicio_observaciones' => $request->ejercicio_observaciones,
                
                // Gineco-obstétricos
                'metodo_conceptivo' => $request->metodo_conceptivo,
                'metodo_conceptivo_cual' => $request->metodo_conceptivo_cual,
                'embarazo_actual' => $request->embarazo_actual,
                'semanas_gestacion' => $request->semanas_gestacion,
                'climatero' => $request->climatero,
                
                // Evaluación dietética
                'tolerancia_via_oral' => $request->tolerancia_via_oral,
                'percepcion_apetito' => $request->percepcion_apetito,
                'percepcion_apetito_observacion' => $request->percepcion_apetito_observacion,
                'alimentos_preferidos' => $request->alimentos_preferidos,
                'alimentos_rechazados' => $request->alimentos_rechazados,
                'suplemento_nutricionales' => $request->suplemento_nutricionales,
                'dieta_especial' => $request->dieta_especial,
                'dieta_especial_cual' => $request->dieta_especial_cual,
                
                // Horarios de comida
                'desayuno_hora' => $request->desayuno_hora,
                'desayuno_hora_observacion' => $request->desayuno_hora_observacion,
                'media_manana_hora' => $request->media_manana_hora,
                'media_manana_hora_observacion' => $request->media_manana_hora_observacion,
                'almuerzo_hora' => $request->almuerzo_hora,
                'almuerzo_hora_observacion' => $request->almuerzo_hora_observacion,
                'media_tarde_hora' => $request->media_tarde_hora,
                'media_tarde_hora_observacion' => $request->media_tarde_hora_observacion,
                'cena_hora' => $request->cena_hora,
                'cena_hora_observacion' => $request->cena_hora_observacion,
                'refrigerio_nocturno_hora' => $request->refrigerio_nocturno_hora,
                'refrigerio_nocturno_hora_observacion' => $request->refrigerio_nocturno_hora_observacion,
                
                // Plan nutricional
                'peso_ideal' => $request->peso_ideal,
                'interpretacion' => $request->interpretacion,
                'meta_meses' => $request->meta_meses,
                'analisis_nutricional' => $request->analisis_nutricional,
                'plan_seguir' => $request->plan_seguir,
            ]);

            \Log::info('✅ Tabla complementaria creada (PRIMERA VEZ - Evaluación inicial completa)');
            
        } else { // CONTROL
            // ✅ CONTROL: Recordatorio 24h y frecuencia de consumo
            \App\Models\HistoriaClinicaComplementaria::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                
                // Antecedentes básicos
                'enfermedad_diagnostica' => $request->enfermedad_diagnostica,
                'habito_intestinal' => $request->habito_intestinal,
                
                // Recordatorio 24 horas
                'comida_desayuno' => $request->comida_desayuno,
                'comida_medio_desayuno' => $request->comida_medio_desayuno,
                'comida_almuerzo' => $request->comida_almuerzo,
                'comida_medio_almuerzo' => $request->comida_medio_almuerzo,
                'comida_cena' => $request->comida_cena,
                
                // Frecuencia de consumo
                'lacteo' => $request->lacteo,
                'lacteo_observacion' => $request->lacteo_observacion,
                'huevo' => $request->huevo,
                'huevo_observacion' => $request->huevo_observacion,
                'embutido' => $request->embutido,
                'embutido_observacion' => $request->embutido_observacion,
                'carne_roja' => $request->carne_roja,
                'carne_blanca' => $request->carne_blanca,
                'carne_vicera' => $request->carne_vicera,
                'carne_observacion' => $request->carne_observacion,
                'leguminosas' => $request->leguminosas,
                'leguminosas_observacion' => $request->leguminosas_observacion,
                'frutas_jugo' => $request->frutas_jugo,
                'frutas_porcion' => $request->frutas_porcion,
                'frutas_observacion' => $request->frutas_observacion,
                'verduras_hortalizas' => $request->verduras_hortalizas,
                'vh_observacion' => $request->vh_observacion,
                'cereales' => $request->cereales,
                'cereales_observacion' => $request->cereales_observacion,
                'rtp' => $request->rtp,
                'rtp_observacion' => $request->rtp_observacion,
                'azucar_dulce' => $request->azucar_dulce,
                'ad_observacion' => $request->ad_observacion,
                
                // Plan de seguimiento
                'diagnostico_nutri' => $request->diagnostico_nutri,
                'plan_seguir_nutri' => $request->plan_seguir_nutri,
                'analisis_nutricional' => $request->analisis_nutricional,
            ]);

            \Log::info('✅ Tabla complementaria creada (CONTROL - Recordatorio 24h y frecuencia)');
        }

        // ✅ PROCESAR DIAGNÓSTICOS (AMBOS TIPOS)
        $diagnosticosProcesados = [];
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos NUTRICIONISTA', [
                'count' => count($request->diagnosticos)
            ]);
            
            foreach ($request->diagnosticos as $index => $diag) {
                if (!empty($diag['diagnostico_id'])) {
                    $diagnostico = \App\Models\Diagnostico::where('uuid', $diag['diagnostico_id'])
                        ->orWhere('id', $diag['diagnostico_id'])
                        ->first();
                    
                    if ($diagnostico && !in_array($diagnostico->id, $diagnosticosProcesados)) {
                        \App\Models\HistoriaDiagnostico::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'diagnostico_id' => $diagnostico->id,
                            'tipo' => $diag['tipo'] ?? ($index === 0 ? 'PRINCIPAL' : 'SECUNDARIO'),
                            'tipo_diagnostico' => $diag['tipo_diagnostico'] ?? 'IMPRESION_DIAGNOSTICA',
                        ]);
                        $diagnosticosProcesados[] = $diagnostico->id;
                        \Log::info('✅ Diagnóstico NUTRICIONISTA guardado', [
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR MEDICAMENTOS (AMBOS TIPOS)
        if ($request->has('medicamentos') && is_array($request->medicamentos)) {
            \Log::info('🔍 Procesando medicamentos NUTRICIONISTA', [
                'count' => count($request->medicamentos)
            ]);
            
            foreach ($request->medicamentos as $med) {
                $medicamentoId = $med['medicamento_id'] ?? null;
                
                if (!empty($medicamentoId)) {
                    $medicamento = \App\Models\Medicamento::where('uuid', $medicamentoId)
                        ->orWhere('id', $medicamentoId)
                        ->first();
                    
                    if ($medicamento) {
                        \App\Models\HistoriaMedicamento::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'medicamento_id' => $medicamento->id,
                            'cantidad' => $med['cantidad'] ?? '1',
                            'dosis' => $med['dosis'] ?? 'Según indicación nutricional',
                        ]);
                        \Log::info('✅ Medicamento NUTRICIONISTA guardado', [
                            'medicamento_id' => $medicamento->id,
                            'nombre' => $medicamento->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES (AMBOS TIPOS)
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones NUTRICIONISTA', [
                'count' => count($request->remisiones)
            ]);
            
            foreach ($request->remisiones as $rem) {
                $remisionId = $rem['remision_id'] ?? null;
                
                if (!empty($remisionId)) {
                    $remision = \App\Models\Remision::where('uuid', $remisionId)
                        ->orWhere('id', $remisionId)
                        ->first();
                    
                    if ($remision) {
                        \App\Models\HistoriaRemision::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'remision_id' => $remision->id,
                            'observacion' => $rem['observacion'] ?? null,
                        ]);
                        \Log::info('✅ Remisión NUTRICIONISTA guardada', [
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    }
                }
            }
        }

        DB::commit();

        // ✅ CARGAR RELACIONES (SIEMPRE INCLUYE COMPLEMENTARIA)
        $historia->load([
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'complementaria' // ✅ Siempre se carga porque ambos tipos la usan
        ]);

        \Log::info('✅ Historia de nutricionista guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => true,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Historia clínica de nutricionista ({$request->tipo_consulta}) guardada exitosamente",
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de nutricionista', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de nutricionista',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}


/**
 * ✅ MÉTODO HELPER PARA OBTENER CITA_ID DESDE UUID
 */
private function getCitaIdFromUuid($citaUuid)
{
    $cita = \App\Models\Cita::where('uuid', $citaUuid)->first();
    return $cita ? $cita->id : null;
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
                'historiaDiagnosticos.diagnostico',   
                'historiaMedicamentos.medicamento',   
                'historiaCups.cups',                   
                'historiaRemisiones.remision',       
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
            'motivo_consulta' => 'nullable|string',
            'enfermedad_actual' => 'nullable|string'
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
     * ✅ GET DIAGNÓSTICOS - CORREGIDO
     */
    public function getDiagnosticos($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $diagnosticos = $historia->historiaDiagnosticos()->with('diagnostico')->get(); // ✅ CORREGIDO

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
     * ✅ ADD DIAGNÓSTICO - CORREGIDO
     */
    public function addDiagnostico(Request $request, $uuid)
    {
        $request->validate([
            'diagnostico_id' => 'required|exists:diagnosticos,id',
            'tipo' => 'required|in:PRINCIPAL,SECUNDARIO'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $diagnosticoHistoria = \App\Models\HistoriaDiagnostico::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                'diagnostico_id' => $request->diagnostico_id,
                'tipo' => $request->tipo,
                'tipo_diagnostico' => $request->tipo_diagnostico ?? 'IMPRESION_DIAGNOSTICA'
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
     * ✅ REMOVE DIAGNÓSTICO - CORREGIDO
     */
    public function removeDiagnostico($uuid, $diagnosticoUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $diagnostico = $historia->historiaDiagnosticos()->where('uuid', $diagnosticoUuid)->firstOrFail(); // ✅ CORREGIDO
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
     * ✅ GET MEDICAMENTOS - CORREGIDO
     */
    public function getMedicamentos($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $medicamentos = $historia->historiaMedicamentos()->with('medicamento')->get(); // ✅ CORREGIDO

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
     * ✅ ADD MEDICAMENTO - CORREGIDO
     */
    public function addMedicamento(Request $request, $uuid)
    {
        $request->validate([
            'medicamento_id' => 'required|exists:medicamentos,id',
            'cantidad' => 'required|string',
            'dosis' => 'required|string'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $medicamentoHistoria = \App\Models\HistoriaMedicamento::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                'medicamento_id' => $request->medicamento_id,
                'cantidad' => $request->cantidad,
                'dosis' => $request->dosis
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
     * ✅ REMOVE MEDICAMENTO - CORREGIDO
     */
    public function removeMedicamento($uuid, $medicamentoUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $medicamento = $historia->historiaMedicamentos()->where('uuid', $medicamentoUuid)->firstOrFail(); // ✅ CORREGIDO
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
     * ✅ GET CUPS - CORREGIDO
     */
    public function getCups($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $cups = $historia->historiaCups()->with('cups')->get(); // ✅ CORREGIDO

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
     * ✅ ADD CUPS - CORREGIDO
     */
    public function addCups(Request $request, $uuid)
    {
        $request->validate([
            'cups_id' => 'required',
            'cantidad' => 'required|integer|min:1'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $cupsHistoria = \App\Models\HistoriaCups::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                'cups_id' => $request->cups_id,
                'observacion' => $request->observacion,
                'cantidad' => $request->cantidad
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
     * ✅ REMOVE CUPS - CORREGIDO
     */
    public function removeCups($uuid, $cupsUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $cups = $historia->historiaCups()->where('uuid', $cupsUuid)->firstOrFail(); // ✅ CORREGIDO
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
     * ✅ GET REMISIONES - CORREGIDO
     */
    public function getRemisiones($uuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $remisiones = $historia->historiaRemisiones()->with('remision')->get(); // ✅ CORREGIDO

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
     * ✅ ADD REMISIÓN - CORREGIDO
     */
    public function addRemision(Request $request, $uuid)
    {
        $request->validate([
            'remision_id' => 'required|exists:remisiones,id',
            'observacion' => 'nullable|string'
        ]);

        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();

            $remisionHistoria = \App\Models\HistoriaRemision::create([
                'uuid' => Str::uuid(),
                'historia_clinica_id' => $historia->id,
                'remision_id' => $request->remision_id,
                'observacion' => $request->observacion
            ]);

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
     * ✅ REMOVE REMISIÓN - CORREGIDO
     */
    public function removeRemision($uuid, $remisionUuid)
    {
        try {
            $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
            $remision = $historia->historiaRemisiones()->where('uuid', $remisionUuid)->firstOrFail(); // ✅ CORREGIDO
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
     * ✅ HISTORIAL PACIENTE - CORREGIDO
     */
    public function historialPaciente($pacienteId)
    {
        try {
            $paciente = Paciente::findOrFail($pacienteId);
            
            $historias = HistoriaClinica::with([
                'sede',
                'cita.paciente',
                'historiaDiagnosticos.diagnostico',    // ✅ CORREGIDO
                'historiaMedicamentos.medicamento',    // ✅ CORREGIDO
                'incapacidades.diagnostico'
            ])
           // ✅ POR ESTO:
->whereHas('cita', function($query) use ($paciente) {
    $query->where('paciente_uuid', $paciente->uuid);
})
            ->orderBy('created_at', 'desc')
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
     * ✅ HISTORIAS PACIENTE - CORREGIDO
     */
    public function historiasPaciente($pacienteUuid)
    {
        try {
            // Buscar paciente por UUID
            $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
            
            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ], 404);
            }

           // ✅ POR ESTO:
            $historias = HistoriaClinica::whereHas('cita', function($query) use ($paciente) {
                $query->where('paciente_uuid', $paciente->uuid);
            })
            ->with([
                'sede',
                'cita.paciente',
                'historiaDiagnosticos.diagnostico',    // ✅ CORREGIDO
                'historiaMedicamentos.medicamento'     // ✅ CORREGIDO
            ])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $historias
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historias del paciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 /**
 * ✅ DETERMINAR VISTA - VERSIÓN CORREGIDA
 */
public function determinarVistaHistoriaClinica(Request $request, string $citaUuid)
{
    try {
        Log::info('🔍 API: Determinando vista de historia clínica', [
            'cita_uuid' => $citaUuid
        ]);

        // ✅ OBTENER DATOS DE LA CITA
        $cita = \App\Models\Cita::with([
            'paciente',
            'agenda.usuarioMedico.especialidad'
        ])->where('uuid', $citaUuid)->first();

        if (!$cita) {
            return response()->json([
                'success' => false,
                'message' => 'Cita no encontrada'
            ], 404);
        }

        // ✅ OBTENER ESPECIALIDAD
        $especialidad = $cita->agenda->usuarioMedico->especialidad->nombre ?? 'MEDICINA GENERAL';
        
        Log::info('🔍 Especialidad detectada', [
            'especialidad' => $especialidad,
            'medico' => $cita->agenda->usuarioMedico->nombre_completo ?? 'N/A'
        ]);

        // ✅ VERIFICAR HISTORIAS ANTERIORES
        $tieneHistoriasAnteriores = $this->verificarHistoriasAnterioresPorEspecialidad(
            $cita->paciente->uuid, 
            $especialidad
        );

        $tipoConsulta = $tieneHistoriasAnteriores ? 'CONTROL' : 'PRIMERA VEZ';

        // ✅ OBTENER HISTORIA PREVIA - AHORA CORREGIDO
        $historiaPrevia = null;
        if ($tipoConsulta === 'CONTROL') {
            $historiaPrevia = $this->obtenerUltimaHistoriaPorEspecialidad(
                $cita->paciente->uuid, 
                $especialidad
            );
            
            // ✅ LOG PARA VERIFICAR QUE LLEGAN LOS DATOS
            if ($historiaPrevia) {
                Log::info('✅ Historia previa obtenida correctamente', [
                    'medicamentos_count' => count($historiaPrevia['medicamentos'] ?? []),
                    'diagnosticos_count' => count($historiaPrevia['diagnosticos'] ?? []),
                    'remisiones_count' => count($historiaPrevia['remisiones'] ?? []),
                    'cups_count' => count($historiaPrevia['cups'] ?? []),
                    'tiene_test_morisky' => !empty($historiaPrevia['test_morisky_olvida_tomar_medicamentos'])
                ]);
            }
        }

        // ✅ DETERMINAR VISTA
        $vistaInfo = $this->determinarVistaSegunEspecialidad($especialidad, $tipoConsulta);

        return response()->json([
            'success' => true,
            'data' => [
                'cita' => $cita,
                'especialidad' => $especialidad,
                'tipo_consulta' => $tipoConsulta,
                'vista_recomendada' => $vistaInfo,
                'historia_previa' => $historiaPrevia, // ✅ AHORA CON DATOS CORRECTOS
                'tiene_historias_anteriores' => $tieneHistoriasAnteriores
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error determinando vista de historia clínica', [
            'error' => $e->getMessage(),
            'cita_uuid' => $citaUuid,
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error determinando vista de historia clínica',
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * ✅ VERIFICAR HISTORIAS ANTERIORES - VERSIÓN CORREGIDA PARA UUID
 */
private function verificarHistoriasAnterioresPorEspecialidad(string $pacienteUuid, string $especialidad): bool
{
    try {
        Log::info('🔍 Verificando historias anteriores - VERSIÓN UUID CORREGIDA', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad
        ]);

        // ✅ PASO 1: Buscar paciente por UUID
        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            Log::warning('⚠️ Paciente no encontrado', ['paciente_uuid' => $pacienteUuid]);
            return false;
        }

        Log::info('✅ Paciente encontrado', [
            'paciente_id' => $paciente->id,
            'paciente_uuid' => $paciente->uuid,
            'paciente_nombre' => $paciente->nombre_completo
        ]);

        // ✅ PASO 2: CORREGIDO - Buscar citas usando PACIENTE_UUID (no paciente_id)
        $citasDelPaciente = \App\Models\Cita::where('paciente_uuid', $paciente->uuid)
            ->where('estado', '!=', 'CANCELADA') // Excluir canceladas
            ->get();

        Log::info('🔍 Citas del paciente encontradas (UUID CORREGIDO)', [
            'paciente_id' => $paciente->id,
            'paciente_uuid' => $paciente->uuid,
            'total_citas' => $citasDelPaciente->count(),
            'citas_ids' => $citasDelPaciente->pluck('id')->toArray(),
            'metodo_busqueda' => 'paciente_uuid (CORREGIDO)'
        ]);

        if ($citasDelPaciente->isEmpty()) {
            Log::info('ℹ️ Paciente no tiene citas - PRIMERA VEZ', [
                'paciente_uuid' => $paciente->uuid
            ]);
            return false;
        }

        // ✅ PASO 3: Buscar historias clínicas de esas citas
        $citasIds = $citasDelPaciente->pluck('id')->toArray();
        
        $historiasDelPaciente = \App\Models\HistoriaClinica::whereIn('cita_id', $citasIds)->get();

        Log::info('🔍 Historias del paciente encontradas (UUID CORREGIDO)', [
            'paciente_uuid' => $paciente->uuid,
            'citas_ids' => $citasIds,
            'total_historias' => $historiasDelPaciente->count(),
            'historias_ids' => $historiasDelPaciente->pluck('id')->toArray()
        ]);

        // ✅ PASO 4: Determinar tipo de consulta
        $tieneHistorias = $historiasDelPaciente->count() > 0;
        $tipoConsulta = $tieneHistorias ? 'CONTROL' : 'PRIMERA VEZ';

        Log::info('✅ RESULTADO FINAL (UUID CORREGIDO)', [
            'paciente_uuid' => $pacienteUuid,
            'paciente_id' => $paciente->id,
            'total_citas' => $citasDelPaciente->count(),
            'total_historias' => $historiasDelPaciente->count(),
            'tiene_historias' => $tieneHistorias,
            'tipo_consulta' => $tipoConsulta,
            'especialidad' => $especialidad,
            'metodo_usado' => 'paciente_uuid (CORREGIDO)'
        ]);

        return $tieneHistorias;

    } catch (\Exception $e) {
        Log::error('❌ Error verificando historias por especialidad - UUID CORREGIDO', [
            'error' => $e->getMessage(),
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return false;
    }
}
/**
 * ✅ OBTENER ÚLTIMA HISTORIA - VERSIÓN CORREGIDA COMPLETA
 */
private function obtenerUltimaHistoriaPorEspecialidad(string $pacienteUuid, string $especialidad): ?array
{
    try {
        Log::info('🔍 Obteniendo última historia - VERSIÓN CORREGIDA', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad
        ]);

        // ✅ PASO 1: Buscar paciente por UUID
        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            Log::warning('⚠️ Paciente no encontrado', ['paciente_uuid' => $pacienteUuid]);
            return null;
        }

        // ✅ PASO 2: Buscar la última historia CON RELACIONES CARGADAS
        $ultimaHistoria = \App\Models\HistoriaClinica::with([
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups'
        ])
        ->whereHas('cita', function($query) use ($paciente) {
            $query->where('paciente_uuid', $paciente->uuid);
        })
        ->orderBy('created_at', 'desc')
        ->first();

        if (!$ultimaHistoria) {
            Log::info('ℹ️ No se encontró historia previa', [
                'paciente_uuid' => $paciente->uuid
            ]);
            return null;
        }

        Log::info('✅ Historia encontrada con relaciones', [
            'historia_uuid' => $ultimaHistoria->uuid,
            'diagnosticos_count' => $ultimaHistoria->historiaDiagnosticos->count(),
            'medicamentos_count' => $ultimaHistoria->historiaMedicamentos->count(),
            'remisiones_count' => $ultimaHistoria->historiaRemisiones->count(),
            'cups_count' => $ultimaHistoria->historiaCups->count()
        ]);

        // ✅ PASO 3: PROCESAR CON EL MÉTODO QUE FUNCIONA
        $historiaFormateada = $this->procesarHistoriaParaFrontend($ultimaHistoria);

        Log::info('✅ Historia procesada para frontend', [
            'historia_uuid' => $ultimaHistoria->uuid,
            'medicamentos_procesados' => count($historiaFormateada['medicamentos'] ?? []),
            'diagnosticos_procesados' => count($historiaFormateada['diagnosticos'] ?? []),
            'remisiones_procesadas' => count($historiaFormateada['remisiones'] ?? []),
            'cups_procesados' => count($historiaFormateada['cups'] ?? []),
            'tiene_test_morisky' => !empty($historiaFormateada['test_morisky_olvida_tomar_medicamentos'])
        ]);

        return $historiaFormateada;

    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo última historia', [
            'error' => $e->getMessage(),
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return null;
    }
}

/**
 * ✅ DETERMINAR VISTA SEGÚN ESPECIALIDAD
 */
private function determinarVistaSegunEspecialidad(string $especialidad, string $tipoConsulta): array
{
    $especialidadesConComplementaria = [
        'REFORMULACION', 'NUTRICIONISTA', 'PSICOLOGIA', 'NEFROLOGIA', 
        'INTERNISTA', 'FISIOTERAPIA', 'TRABAJO SOCIAL'
    ];

    $usaComplementaria = in_array($especialidad, $especialidadesConComplementaria);

    // ✅ MAPEO DE VISTAS
    $vistas = [
        'MEDICINA GENERAL' => [
            'PRIMERA VEZ' => 'medicina-general.primera-vez',
            'CONTROL' => 'medicina-general.control'
        ],
        'REFORMULACION' => [
            'PRIMERA VEZ' => 'reformulacion.primera-vez',
            'CONTROL' => 'reformulacion.control'
        ],
        'NUTRICIONISTA' => [
            'PRIMERA VEZ' => 'nutricionista.primera-vez',
            'CONTROL' => 'nutricionista.control'
        ],
        'PSICOLOGIA' => [
            'PRIMERA VEZ' => 'psicologia.primera-vez',
            'CONTROL' => 'psicologia.control'
        ],
        'NEFROLOGIA' => [
            'PRIMERA VEZ' => 'nefrologia.primera-vez',
            'CONTROL' => 'nefrologia.control'
        ],
        'INTERNISTA' => [
            'PRIMERA VEZ' => 'internista.primera-vez',
            'CONTROL' => 'internista.control'
        ],
        'FISIOTERAPIA' => [
            'PRIMERA VEZ' => 'fisioterapia.primera-vez',
            'CONTROL' => 'fisioterapia.control'
        ],
        'TRABAJO SOCIAL' => [
            'PRIMERA VEZ' => 'trabajo-social.primera-vez',
            'CONTROL' => 'trabajo-social.control'
        ]
    ];

    $vistaEspecifica = $vistas[$especialidad][$tipoConsulta] ?? $vistas['MEDICINA GENERAL'][$tipoConsulta];

    return [
        'vista' => $vistaEspecifica,
        'usa_complementaria' => $usaComplementaria,
        'especialidad' => $especialidad,
        'tipo_consulta' => $tipoConsulta
    ];
}
/**
 * ✅ MÉTODO DE DEBUG - VERIFICAR DATOS DEL PACIENTE - CORREGIDO
 */
public function debugPacienteHistorias(Request $request, string $pacienteUuid)
{
    try {
        Log::info('🔍 DEBUG: Iniciando verificación de paciente - MÉTODO CORREGIDO', [
            'paciente_uuid' => $pacienteUuid
        ]);

        // ✅ PASO 1: Buscar paciente por UUID
        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado',
                'paciente_uuid' => $pacienteUuid
            ]);
        }

        Log::info('✅ Paciente encontrado', [
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre_completo
        ]);

        // ✅ PASO 2: Buscar citas del paciente (por ID)
        $citas = \App\Models\Cita::where('paciente_uuid', $paciente->uuid)
            ->with(['agenda.usuarioMedico.especialidad'])
            ->get();

        Log::info('🔍 Citas encontradas', [
            'paciente_id' => $paciente->id,
            'total_citas' => $citas->count()
        ]);

        // ✅ PASO 3: Buscar historias de esas citas
        $citasIds = $citas->pluck('id')->toArray();
        
        $historias = \App\Models\HistoriaClinica::whereIn('cita_id', $citasIds)
            ->with(['cita'])
            ->get();

        Log::info('🔍 Historias encontradas', [
            'paciente_id' => $paciente->id,
            'citas_ids' => $citasIds,
            'total_historias' => $historias->count()
        ]);

        // ✅ PASO 4: Verificar directamente en base de datos
        $historiasDirectas = \DB::table('historias_clinicas as hc')
            ->join('citas as c', 'hc.cita_id', '=', 'c.id')
            ->where('c.paciente_id', $paciente->id)
            ->select('hc.id', 'hc.uuid', 'hc.created_at', 'c.paciente_id', 'hc.cita_id')
            ->get();

        Log::info('🔍 Historias directas desde DB', [
            'paciente_id' => $paciente->id,
            'total_historias_directas' => $historiasDirectas->count()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'paciente' => [
                    'id' => $paciente->id,
                    'uuid' => $paciente->uuid,
                    'nombre_completo' => $paciente->nombre_completo,
                    'documento' => $paciente->documento
                ],
                'total_citas' => $citas->count(),
                'total_historias' => $historias->count(),
                'total_historias_directas' => $historiasDirectas->count(),
                'citas' => $citas,
                'historias' => $historias,
                'historias_directas' => $historiasDirectas,
                'debug_info' => [
                    'deberia_ser_control' => $historias->count() > 0,
                    'tipo_consulta_esperado' => $historias->count() > 0 ? 'CONTROL' : 'PRIMERA VEZ',
                    'metodo_usado' => 'whereIn con citas_ids del paciente',
                    'flujo_correcto' => 'Paciente UUID → Paciente ID → Citas IDs → Historias'
                ]
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error en debug de paciente - MÉTODO CORREGIDO', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ], 500);
    }
}

  /**
 * ✅ OBTENER ÚLTIMA HISTORIA - SOLO CON TUS DATOS
 */
public function obtenerUltimaHistoriaMedicinaGeneral(Request $request, string $pacienteUuid)
{
    try {
        Log::info('🔍 Obteniendo última historia para Medicina General', [
            'paciente_uuid' => $pacienteUuid
        ]);

        // ✅ BUSCAR PACIENTE POR UUID
        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ], 404);
        }

        // ✅ BUSCAR CITAS DEL PACIENTE
        $citas = \App\Models\Cita::where('paciente_uuid', $paciente->uuid)
            ->where('estado', '!=', 'CANCELADA')
            ->get();

        if ($citas->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay historias previas - Primera vez'
            ]);
        }

        // ✅ BUSCAR ÚLTIMA HISTORIA CLÍNICA CON RELACIONES
        $citasIds = $citas->pluck('id')->toArray();
        
        $ultimaHistoria = \App\Models\HistoriaClinica::with([
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups'
        ])
        ->whereIn('cita_id', $citasIds)
        ->orderBy('created_at', 'desc')
        ->first();

        if (!$ultimaHistoria) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay historias previas - Primera vez'
            ]);
        }

        // ✅ PROCESAR DATOS PARA EL FRONTEND
        $historiaPrevia = $this->procesarHistoriaParaFrontend($ultimaHistoria);

        Log::info('✅ Historia previa procesada', [
            'paciente_uuid' => $pacienteUuid,
            'historia_uuid' => $ultimaHistoria->uuid,
            'medicamentos_count' => count($historiaPrevia['medicamentos'] ?? []),
            'diagnosticos_count' => count($historiaPrevia['diagnosticos'] ?? []),
            'remisiones_count' => count($historiaPrevia['remisiones'] ?? []),
            'cups_count' => count($historiaPrevia['cups'] ?? [])
        ]);

        return response()->json([
            'success' => true,
            'data' => $historiaPrevia,
            'message' => 'Historia previa encontrada - Control'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo última historia', [
            'error' => $e->getMessage(),
            'paciente_uuid' => $pacienteUuid,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error obteniendo historia previa',
            'error' => $e->getMessage()
        ], 500);
    }
}


  /**
 * ✅ PROCESAR HISTORIA PARA FRONTEND - SOLO CON LOS DATOS QUE TIENES
 */
private function procesarHistoriaParaFrontend(\App\Models\HistoriaClinica $historia): array
{
    try {
        Log::info('🔧 Procesando historia para frontend', [
            'historia_uuid' => $historia->uuid,
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
            'cups_count' => $historia->historiaCups->count()
        ]);

        return [
            // ✅ MEDICAMENTOS - ESTRUCTURA CORREGIDA
            'medicamentos' => $historia->historiaMedicamentos->map(function($item) {
                return [
                    'medicamento_id' => $item->medicamento->uuid ?? $item->medicamento->id,
                    'cantidad' => $item->cantidad,
                    'dosis' => $item->dosis,
                    'medicamento' => [
                        'uuid' => $item->medicamento->uuid ?? $item->medicamento->id,
                        'nombre' => $item->medicamento->nombre,
                        'principio_activo' => $item->medicamento->principio_activo ?? ''
                    ]
                ];
            })->toArray(),

            // ✅ REMISIONES - ESTRUCTURA CORREGIDA
            'remisiones' => $historia->historiaRemisiones->map(function($item) {
                return [
                    'remision_id' => $item->remision->uuid ?? $item->remision->id,
                    'observacion' => $item->observacion,
                    'remision' => [
                        'uuid' => $item->remision->uuid ?? $item->remision->id,
                        'nombre' => $item->remision->nombre,
                        'tipo' => $item->remision->tipo ?? ''
                    ]
                ];
            })->toArray(),

            // ✅ DIAGNÓSTICOS - ESTRUCTURA CORREGIDA
            'diagnosticos' => $historia->historiaDiagnosticos->map(function($item) {
                return [
                    'diagnostico_id' => $item->diagnostico->uuid ?? $item->diagnostico->id,
                    'tipo' => $item->tipo,
                    'tipo_diagnostico' => $item->tipo_diagnostico,
                    'diagnostico' => [
                        'uuid' => $item->diagnostico->uuid ?? $item->diagnostico->id,
                        'codigo' => $item->diagnostico->codigo,
                        'nombre' => $item->diagnostico->nombre
                    ]
                ];
            })->toArray(),

            // ✅ CUPS - ESTRUCTURA CORREGIDA
            'cups' => $historia->historiaCups->map(function($item) {
                return [
                    'cups_id' => $item->cups->uuid ?? $item->cups->id,
                    'observacion' => $item->observacion,
                    'cups' => [
                        'uuid' => $item->cups->uuid ?? $item->cups->id,
                        'codigo' => $item->cups->codigo,
                        'nombre' => $item->cups->nombre
                    ]
                ];
            })->toArray(),

            // ✅ CLASIFICACIONES - NOMBRES CORRECTOS SEGÚN TU MIGRACIÓN
            'clasificacion_estado_metabolico' => $historia->clasificacion_estado_metabolico,
            'clasificacion_hta' => $historia->clasificacion_hta,
            'clasificacion_dm' => $historia->clasificacion_dm,
            'clasificacion_rcv' => $historia->clasificacion_rcv,
            'clasificacion_erc_estado' => $historia->clasificacion_erc_estado,
            'clasificacion_erc_categoria_ambulatoria_persistente' => $historia->clasificacion_erc_categoria_ambulatoria_persistente,

            // ✅ TASAS DE FILTRACIÓN
            'tasa_filtracion_glomerular_ckd_epi' => $historia->tasa_filtracion_glomerular_ckd_epi,
            'tasa_filtracion_glomerular_gockcroft_gault' => $historia->tasa_filtracion_glomerular_gockcroft_gault,

            // ✅ ANTECEDENTES PERSONALES - NOMBRES CORRECTOS SEGÚN TU MODELO
            'hipertension_arterial_personal' => $historia->hipertension_arterial_personal ?? 'NO',
            'obs_hipertension_arterial_personal' => $historia->obs_personal_hipertension_arterial,
            'diabetes_mellitus_personal' => $historia->diabetes_mellitus_personal ?? 'NO',
            'obs_diabetes_mellitus_personal' => $historia->obs_personal_mellitus,

            // ✅ TALLA
            'talla' => $historia->talla,

            // ✅ TEST DE MORISKY - NOMBRES CORRECTOS SEGÚN TU MODELO
            'test_morisky_olvida_tomar_medicamentos' => $historia->olvida_tomar_medicamentos,
            'test_morisky_toma_medicamentos_hora_indicada' => $historia->toma_medicamentos_hora_indicada,
            'test_morisky_cuando_esta_bien_deja_tomar_medicamentos' => $historia->cuando_esta_bien_deja_tomar_medicamentos,
            'test_morisky_siente_mal_deja_tomarlos' => $historia->siente_mal_deja_tomarlos,
            'test_morisky_valoracio_psicologia' => $historia->valoracion_psicologia,
            'adherente' => $historia->adherente,

                // ✅ EDUCACIÓN EN SALUD
            'alimentacion' => $historia->alimentacion,
            'disminucion_consumo_sal_azucar' => $historia->disminucion_consumo_sal_azucar,
            'fomento_actividad_fisica' => $historia->fomento_actividad_fisica,
            'importancia_adherencia_tratamiento' => $historia->importancia_adherencia_tratamiento,
            'consumo_frutas_verduras' => $historia->consumo_frutas_verduras,
            'manejo_estres' => $historia->manejo_estres,
            'disminucion_consumo_cigarrillo' => $historia->disminucion_consumo_cigarrillo,
            'disminucion_peso' => $historia->disminucion_peso,
        ];

    } catch (\Exception $e) {
        Log::error('❌ Error procesando historia para frontend', [
            'error' => $e->getMessage(),
            'historia_id' => $historia->id ?? 'N/A',
            'line' => $e->getLine()
        ]);
        
        return [
            'medicamentos' => [],
            'remisiones' => [],
            'diagnosticos' => [],
            'cups' => [],
            'clasificacion_estado_metabolico' => null,
            'clasificacion_hta' => null,
            'clasificacion_dm' => null,
            'clasificacion_rcv' => null,
            'clasificacion_erc_estado' => null,
            'clasificacion_erc_categoria_ambulatoria_persistente' => null,
            'tasa_filtracion_glomerular_ckd_epi' => null,
            'tasa_filtracion_glomerular_gockcroft_gault' => null,
            'hipertension_arterial_personal' => 'NO',
            'obs_hipertension_arterial_personal' => null,
            'diabetes_mellitus_personal' => 'NO',
            'obs_diabetes_mellitus_personal' => null,
            'talla' => null,
            'test_morisky_olvida_tomar_medicamentos' => null,
            'test_morisky_toma_medicamentos_hora_indicada' => null,
            'test_morisky_cuando_esta_bien_deja_tomar_medicamentos' => null,
            'test_morisky_siente_mal_deja_tomarlos' => null,
            'test_morisky_valoracio_psicologia' => null,
            'adherente' => null,
            'alimentacion' => null,
            'disminucion_consumo_sal_azucar' => null,
            'fomento_actividad_fisica' => null,
            'importancia_adherencia_tratamiento' => null,
            'consumo_frutas_verduras' => null,
            'manejo_estres' => null,
            'disminucion_consumo_cigarrillo' => null,
            'disminucion_peso' => null,
        ];
    }
}

/**
 * ✅ FORMATEAR HISTORIA PREVIA DESDE API PARA EL FORMULARIO - CORREGIDO
 */
private function formatearHistoriaDesdeAPI(array $historiaAPI): array
{
    try {
        Log::info('🔧 Formateando historia desde API', [
            'keys_disponibles' => array_keys($historiaAPI),
            'tiene_medicamentos' => !empty($historiaAPI['historia_medicamentos']),
            'tiene_diagnosticos' => !empty($historiaAPI['historia_diagnosticos'])
        ]);

        $historiaFormateada = [
            // ✅ TEST DE MORISKY
            'test_morisky_olvida_tomar_medicamentos' => $historiaAPI['olvida_tomar_medicamentos'] ?? 'NO',
            'test_morisky_toma_medicamentos_hora_indicada' => $historiaAPI['toma_medicamentos_hora_indicada'] ?? 'NO',
            'test_morisky_cuando_esta_bien_deja_tomar_medicamentos' => $historiaAPI['cuando_esta_bien_deja_tomar_medicamentos'] ?? 'NO',
            'test_morisky_siente_mal_deja_tomarlos' => $historiaAPI['siente_mal_deja_tomarlos'] ?? 'NO',
            'test_morisky_valoracio_psicologia' => $historiaAPI['valoracion_psicologia'] ?? 'NO',
            'adherente' => $historiaAPI['adherente'] ?? 'NO',

            // ✅ ANTECEDENTES PERSONALES
            'hipertension_arterial_personal' => $historiaAPI['hipertension_arterial_personal'] ?? 'NO',
            'obs_hipertension_arterial_personal' => $historiaAPI['obs_personal_hipertension_arterial'] ?? '',
            'diabetes_mellitus_personal' => $historiaAPI['diabetes_mellitus_personal'] ?? 'NO',
            'obs_diabetes_mellitus_personal' => $historiaAPI['obs_personal_mellitus'] ?? '',

            // ✅ CLASIFICACIONES
            'clasificacion_estado_metabolico' => $historiaAPI['clasificacion_estado_metabolico'] ?? '',
            'clasificacion_hta' => $historiaAPI['clasificacion_hta'] ?? '',
            'clasificacion_dm' => $historiaAPI['clasificacion_dm'] ?? '',
            'clasificacion_rcv' => $historiaAPI['clasificacion_rcv'] ?? '',
            'clasificacion_erc_estado' => $historiaAPI['clasificacion_erc_estado'] ?? '',
            'clasificacion_erc_categoria_ambulatoria_persistente' => $historiaAPI['clasificacion_erc_categoria_ambulatoria_persistente'] ?? '',

            // ✅ TASAS DE FILTRACIÓN
            'tasa_filtracion_glomerular_ckd_epi' => $historiaAPI['tasa_filtracion_glomerular_ckd_epi'] ?? '',
            'tasa_filtracion_glomerular_gockcroft_gault' => $historiaAPI['tasa_filtracion_glomerular_gockcroft_gault'] ?? '',

            // ✅ TALLA
            'talla' => $historiaAPI['talla'] ?? '',

              // ✅ EDUCACIÓN EN SALUD
            'alimentacion' => $historiaAPI['alimentacion'] ?? null,
            'disminucion_consumo_sal_azucar' => $historiaAPI['disminucion_consumo_sal_azucar'] ?? null,
            'fomento_actividad_fisica' => $historiaAPI['fomento_actividad_fisica'] ?? null,
            'importancia_adherencia_tratamiento' => $historiaAPI['importancia_adherencia_tratamiento'] ?? null,
            'consumo_frutas_verduras' => $historiaAPI['consumo_frutas_verduras'] ?? null,
            'manejo_estres' => $historiaAPI['manejo_estres'] ?? null,
            'disminucion_consumo_cigarrillo' => $historiaAPI['disminucion_consumo_cigarrillo'] ?? null,
            'disminucion_peso' => $historiaAPI['disminucion_peso'] ?? null,

            
            // ✅ MEDICAMENTOS - USAR NOMBRES CORRECTOS
            'medicamentos' => $this->formatearMedicamentosDesdeAPI($historiaAPI['historia_medicamentos'] ?? []),

            // ✅ REMISIONES - USAR NOMBRES CORRECTOS
            'remisiones' => $this->formatearRemisionesDesdeAPI($historiaAPI['historia_remisiones'] ?? []),

            // ✅ DIAGNÓSTICOS - USAR NOMBRES CORRECTOS
            'diagnosticos' => $this->formatearDiagnosticosDesdeAPI($historiaAPI['historia_diagnosticos'] ?? []),

            // ✅ CUPS - USAR NOMBRES CORRECTOS
            'cups' => $this->formatearCupsDesdeAPI($historiaAPI['historia_cups'] ?? []),
        ];

        Log::info('✅ Historia formateada desde API', [
            'campos_totales' => count($historiaFormateada),
            'medicamentos_count' => count($historiaFormateada['medicamentos']),
            'diagnosticos_count' => count($historiaFormateada['diagnosticos']),
            'remisiones_count' => count($historiaFormateada['remisiones']),
            'cups_count' => count($historiaFormateada['cups'])
        ]);

        return $historiaFormateada;

    } catch (\Exception $e) {
        Log::error('❌ Error formateando historia desde API', [
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

// ✅ MÉTODOS AUXILIARES DE FORMATEO
private function formatearMedicamentosDesdeAPI(array $medicamentos): array
{
    return array_map(function($medicamento) {
        return [
            'medicamento_id' => $medicamento['medicamento_id'] ?? $medicamento['medicamento']['uuid'] ?? $medicamento['medicamento']['id'],
            'cantidad' => $medicamento['cantidad'] ?? '',
            'dosis' => $medicamento['dosis'] ?? '',
            'medicamento' => [
                'uuid' => $medicamento['medicamento']['uuid'] ?? $medicamento['medicamento']['id'],
                'nombre' => $medicamento['medicamento']['nombre'] ?? '',
                'principio_activo' => $medicamento['medicamento']['principio_activo'] ?? ''
            ]
        ];
    }, $medicamentos);
}

private function formatearRemisionesDesdeAPI(array $remisiones): array
{
    return array_map(function($remision) {
        return [
            'remision_id' => $remision['remision_id'] ?? $remision['remision']['uuid'] ?? $remision['remision']['id'],
            'observacion' => $remision['observacion'] ?? '',
            'remision' => [
                'uuid' => $remision['remision']['uuid'] ?? $remision['remision']['id'],
                'nombre' => $remision['remision']['nombre'] ?? '',
                'tipo' => $remision['remision']['tipo'] ?? ''
            ]
        ];
    }, $remisiones);
}

private function formatearDiagnosticosDesdeAPI(array $diagnosticos): array
{
    return array_map(function($diagnostico) {
        return [
            'diagnostico_id' => $diagnostico['diagnostico_id'] ?? $diagnostico['diagnostico']['uuid'] ?? $diagnostico['diagnostico']['id'],
            'tipo' => $diagnostico['tipo'] ?? 'PRINCIPAL',
            'tipo_diagnostico' => $diagnostico['tipo_diagnostico'] ?? '',
            'diagnostico' => [
                'uuid' => $diagnostico['diagnostico']['uuid'] ?? $diagnostico['diagnostico']['id'],
                'codigo' => $diagnostico['diagnostico']['codigo'] ?? '',
                'nombre' => $diagnostico['diagnostico']['nombre'] ?? ''
            ]
        ];
    }, $diagnosticos);
}

private function formatearCupsDesdeAPI(array $cups): array
{
    return array_map(function($cup) {
        return [
            'cups_id' => $cup['cups_id'] ?? $cup['cups']['uuid'] ?? $cup['cups']['id'],
            'observacion' => $cup['observacion'] ?? '',
            'cups' => [
                'uuid' => $cup['cups']['uuid'] ?? $cup['cups']['id'],
                'codigo' => $cup['cups']['codigo'] ?? '',
                'nombre' => $cup['cups']['nombre'] ?? ''
            ]
        ];
    }, $cups);
}


}
