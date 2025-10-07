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




public function store(Request $request)
{
    // ✅ VALIDACIÓN (mantener igual)
    $request->validate([
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'cita_uuid' => 'required|string',
        'tipo_consulta' => 'required|in:PRIMERA VEZ,CONTROL,URGENCIAS',
        'motivo_consulta' => 'required|string',
        'enfermedad_actual' => 'required|string',
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

    \Log::info('🔍 DEBUG: Datos recibidos en store', [
        'has_idDiagnostico' => $request->has('idDiagnostico'),
        'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
        'medicamentos_count' => $request->medicamentos ? count($request->medicamentos) : 0,
        'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
        'cups_count' => $request->cups ? count($request->cups) : 0,
    ]);

    DB::beginTransaction();
    try {
        // ✅ OBTENER CITA
        $cita = \App\Models\Cita::where('uuid', $request->cita_uuid)->first();
        if (!$cita) {
            throw new \Exception('Cita no encontrada con UUID: ' . $request->cita_uuid);
        }

        // ✅ CREAR HISTORIA - SOLO CAMPOS QUE EXISTEN EN TU MIGRACIÓN
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // ✅ TODOS TUS CAMPOS EXISTENTES
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'acompanante' => $request->acompanante,
            'acu_telefono' => $request->acu_telefono,
            'acu_parentesco' => $request->acu_parentesco,
            'causa_externa' => $request->causa_externa,
            'motivo_consulta' => $request->motivo_consulta,
            'enfermedad_actual' => $request->enfermedad_actual,
            'discapacidad_fisica' => $request->discapacidad_fisica ?? 'NO',
            'discapacidad_visual' => $request->discapacidad_visual ?? 'NO',
            'discapacidad_mental' => $request->discapacidad_mental ?? 'NO',
            'discapacidad_auditiva' => $request->discapacidad_auditiva ?? 'NO',
            'discapacidad_intelectual' => $request->discapacidad_intelectual ?? 'NO',
            'drogo_dependiente' => $request->drogo_dependiente ?? 'NO',
            'drogo_dependiente_cual' => $request->drogo_dependiente_cual,
            'peso' => $request->peso,
            'talla' => $request->talla,
            'imc' => $request->imc,
            'clasificacion' => $request->clasificacion,
            'tasa_filtracion_glomerular_ckd_epi' => $request->tasa_filtracion_glomerular_ckd_epi,
            'tasa_filtracion_glomerular_gockcroft_gault' => $request->tasa_filtracion_glomerular_gockcroft_gault,
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
            'hipertension_arterial_personal' => $request->hipertension_arterial_personal ?? 'NO',
            'obs_personal_hipertension_arterial' => $request->obs_personal_hipertension_arterial,
            'diabetes_mellitus_personal' => $request->diabetes_mellitus_personal ?? 'NO',
            'obs_personal_mellitus' => $request->obs_personal_mellitus,
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
            'insulina_requiriente' => $request->insulina_requiriente,
            'olvida_tomar_medicamentos' => $request->olvida_tomar_medicamentos ?? 'NO',
            'toma_medicamentos_hora_indicada' => $request->toma_medicamentos_hora_indicada ?? 'SI',
            'cuando_esta_bien_deja_tomar_medicamentos' => $request->cuando_esta_bien_deja_tomar_medicamentos ?? 'NO',
            'siente_mal_deja_tomarlos' => $request->siente_mal_deja_tomarlos ?? 'NO',
            'valoracion_psicologia' => $request->valoracion_psicologia ?? 'NO',
            'cabeza' => $request->cabeza ?? 'NORMAL',
            'orl' => $request->orl ?? 'NORMAL',
            'cardiovascular' => $request->cardiovascular ?? 'NORMAL',
            'gastrointestinal' => $request->gastrointestinal ?? 'NORMAL',
            'osteoatromuscular' => $request->osteoatromuscular ?? 'NORMAL',
            'snc' => $request->snc ?? 'NORMAL',
            'revision_sistemas' => $request->revision_sistemas,
            'presion_arterial_sistolica_sentado_pie' => $request->presion_arterial_sistolica_sentado_pie,
            'presion_arterial_distolica_sentado_pie' => $request->presion_arterial_distolica_sentado_pie,
            'presion_arterial_sistolica_acostado' => $request->presion_arterial_sistolica_acostado,
            'presion_arterial_distolica_acostado' => $request->presion_arterial_distolica_acostado,
            'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
            'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
            'ef_cabeza' => $request->ef_cabeza ?? 'NORMAL',
            'obs_cabeza' => $request->obs_cabeza,
            'agudeza_visual' => $request->agudeza_visual ?? 'NORMAL',
            'obs_agudeza_visual' => $request->obs_agudeza_visual,
            'fundoscopia' => $request->fundoscopia ?? 'NORMAL',
            'obs_fundoscopia' => $request->obs_fundoscopia,
            'cuello' => $request->cuello ?? 'NORMAL',
            'obs_cuello' => $request->obs_cuello,
            'torax' => $request->torax ?? 'NORMAL',
            'obs_torax' => $request->obs_torax,
            'mamas' => $request->mamas ?? 'NORMAL',
            'obs_mamas' => $request->obs_mamas,
            'abdomen' => $request->abdomen ?? 'NORMAL',
            'obs_abdomen' => $request->obs_abdomen,
            'genito_urinario' => $request->genito_urinario ?? 'NORMAL',
            'obs_genito_urinario' => $request->obs_genito_urinario,
            'extremidades' => $request->extremidades ?? 'NORMAL',
            'obs_extremidades' => $request->obs_extremidades,
            'piel_anexos_pulsos' => $request->piel_anexos_pulsos ?? 'NORMAL',
            'obs_piel_anexos_pulsos' => $request->obs_piel_anexos_pulsos,
            'sistema_nervioso' => $request->sistema_nervioso ?? 'NORMAL',
            'obs_sistema_nervioso' => $request->obs_sistema_nervioso,
            'capacidad_cognitiva' => $request->capacidad_cognitiva ?? 'NORMAL',
            'obs_capacidad_cognitiva' => $request->obs_capacidad_cognitiva,
            'orientacion' => $request->orientacion ?? 'NORMAL',
            'obs_orientacion' => $request->obs_orientacion,
            'reflejo_aquiliar' => $request->reflejo_aquiliar ?? 'NORMAL',
            'obs_reflejo_aquiliar' => $request->obs_reflejo_aquiliar,
            'reflejo_patelar' => $request->reflejo_patelar ?? 'NORMAL',
            'obs_reflejo_patelar' => $request->obs_reflejo_patelar,
            'hallazgo_positivo_examen_fisico' => $request->hallazgo_positivo_examen_fisico,
            'tabaquismo' => $request->tabaquismo ?? 'NO',
            'obs_tabaquismo' => $request->obs_tabaquismo,
            'dislipidemia' => $request->dislipidemia ?? 'NO',
            'obs_dislipidemia' => $request->obs_dislipidemia,
            'menor_cierta_edad' => $request->menor_cierta_edad ?? 'NO',
            'obs_menor_cierta_edad' => $request->obs_menor_cierta_edad,
            'perimetro_abdominal' => $request->perimetro_abdominal,
            'obs_perimetro_abdominal' => $request->obs_perimetro_abdominal,
            'condicion_clinica_asociada' => $request->condicion_clinica_asociada ?? 'NO',
            'obs_condicion_clinica_asociada' => $request->obs_condicion_clinica_asociada,
            'lesion_organo_blanco' => $request->lesion_organo_blanco ?? 'NO',
            'descripcion_lesion_organo_blanco' => $request->descripcion_lesion_organo_blanco,
            'obs_lesion_organo_blanco' => $request->obs_lesion_organo_blanco,
            'clasificacion_hta' => $request->clasificacion_hta,
            'clasificacion_dm' => $request->clasificacion_dm,
            'clasificacion_erc_estado' => $request->clasificacion_erc_estado,
            'clasificacion_erc_categoria_ambulatoria_persistente' => $request->clasificacion_erc_categoria_ambulatoria_persistente,
            'clasificacion_rcv' => $request->clasificacion_rcv,
            'alimentacion' => $request->alimentacion ?? 'NO',
            'disminucion_consumo_sal_azucar' => $request->disminucion_consumo_sal_azucar ?? 'NO',
            'fomento_actividad_fisica' => $request->fomento_actividad_fisica ?? 'NO',
            'importancia_adherencia_tratamiento' => $request->importancia_adherencia_tratamiento ?? 'NO',
            'consumo_frutas_verduras' => $request->consumo_frutas_verduras ?? 'NO',
            'manejo_estres' => $request->manejo_estres ?? 'NO',
            'disminucion_consumo_cigarrillo' => $request->disminucion_consumo_cigarrillo ?? 'NO',
            'disminucion_peso' => $request->disminucion_peso ?? 'NO',
            'observaciones_generales' => $request->observaciones_generales,
            'oidos' => $request->oidos ?? 'NORMAL',
            'nariz_senos_paranasales' => $request->nariz_senos_paranasales ?? 'NORMAL',
            'cavidad_oral' => $request->cavidad_oral ?? 'NORMAL',
            'cardio_respiratorio' => $request->cardio_respiratorio ?? 'NORMAL',
            'musculo_esqueletico' => $request->musculo_esqueletico ?? 'NORMAL',
            'inspeccion_sensibilidad_pies' => $request->inspeccion_sensibilidad_pies ?? 'NORMAL',
            'capacidad_cognitiva_orientacion' => $request->capacidad_cognitiva_orientacion ?? 'NORMAL',
            'recibe_tratamiento_alternativo' => $request->recibe_tratamiento_alternativo ?? 'NO',
            'recibe_tratamiento_con_plantas_medicinales' => $request->recibe_tratamiento_con_plantas_medicinales ?? 'NO',
            'recibe_ritual_medicina_tradicional' => $request->recibe_ritual_medicina_tradicional ?? 'NO',
            'numero_frutas_diarias' => $request->numero_frutas_diarias ?? 0,
            'elevado_consumo_grasa_saturada' => $request->elevado_consumo_grasa_saturada ?? 'NO',
            'adiciona_sal_despues_preparar_comida' => $request->adiciona_sal_despues_preparar_comida ?? 'NO',
            'general' => $request->general,
            'respiratorio' => $request->respiratorio,
            'adherente' => $request->adherente,
            'ecografia_renal' => $request->ecografia_renal,
            'razon_reformulacion' => $request->razon_reformulacion,
            'motivo_reformulacion' => $request->motivo_reformulacion,
            'reformulacion_quien_reclama' => $request->reformulacion_quien_reclama,
            'reformulacion_nombre_reclama' => $request->reformulacion_nombre_reclama,
            'electrocardiograma' => $request->electrocardiograma,
            'ecocardiograma' => $request->ecocardiograma,
            'adicional' => $request->adicional,
            'clasificacion_estado_metabolico' => $request->clasificacion_estado_metabolico,
            'fex_es' => $request->fex_es,
            'fex_es1' => $request->fex_es1,
            'fex_es2' => $request->fex_es2,
        ]);

        // ✅ PROCESAR DIAGNÓSTICOS
        $diagnosticosProcesados = [];
        
        if ($request->idDiagnostico && !empty($request->idDiagnostico)) {
            \Log::info('🔍 Procesando diagnóstico individual', ['idDiagnostico' => $request->idDiagnostico]);
            
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
                \Log::info('✅ Diagnóstico individual guardado', ['diagnostico_id' => $diagnostico->id]);
            }
        }
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos', ['count' => count($request->diagnosticos)]);
            
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
                        \Log::info('✅ Diagnóstico del array guardado', [
                            'index' => $index,
                            'diagnostico_id' => $diagnostico->id
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR MEDICAMENTOS
        if ($request->has('medicamentos') && is_array($request->medicamentos)) {
            \Log::info('🔍 Procesando medicamentos', ['count' => count($request->medicamentos)]);
            
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
                        \Log::info('✅ Medicamento guardado', ['medicamento_id' => $medicamento->id]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones', ['count' => count($request->remisiones)]);
            
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
                        \Log::info('✅ Remisión guardada', ['remision_id' => $remision->id]);
                    }
                }
            }
        }

        // ✅ PROCESAR CUPS
        if ($request->has('cups') && is_array($request->cups)) {
            \Log::info('🔍 Procesando CUPS', ['count' => count($request->cups)]);
            
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
                        \Log::info('✅ CUPS guardado', ['cups_id' => $cupsModel->id]);
                    }
                }
            }
        }

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
            'historia_uuid' => $historia->uuid,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
            'cups_count' => $historia->historiaCups->count()
        ]);

        // ✅ RESPUESTA PARA API SIN REDIRECT_URL
        return response()->json([
            'success' => true,
            'message' => 'Historia clínica creada exitosamente con todos sus componentes',
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error creando historia clínica completa', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica',
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
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
 * ✅ MÉTODO FALTANTE: Obtener historias de un paciente
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

        // Obtener historias del paciente
        $historias = HistoriaClinica::whereHas('cita', function($query) use ($paciente) {
            $query->where('paciente_id', $paciente->id);
        })
        ->with([
            'sede',
            'cita.paciente',
            'diagnosticos.diagnostico',
            'medicamentos.medicamento'
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
   
}
