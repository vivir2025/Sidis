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
    // ✅ VALIDACIÓN (mantener igual)
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

          // ✅ PASO 2: DETECTAR ESPECIALIDAD
        $especialidad = $cita->agenda->usuarioMedico->especialidad->nombre ?? 'MEDICINA GENERAL';
        
        \Log::info('🔍 Especialidad detectada en store', [
            'especialidad' => $especialidad,
            'cita_uuid' => $request->cita_uuid,
            'medico' => $cita->agenda->usuarioMedico->nombre_completo ?? 'N/A'
        ]);

        // ✅ PASO 3: SI ES FISIOTERAPIA, USAR MÉTODO ESPECÍFICO
        if ($especialidad === 'FISIOTERAPIA') {
            DB::rollBack(); // Cancelar transacción actual
            return $this->storeFisioterapia($request, $cita);
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
            'motivo_consulta' => $request->motivo_consulta?? '',
            'enfermedad_actual' => $request->enfermedad_actual?? '',
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
private function storeFisioterapia(Request $request, $cita)
{
    // ✅ VALIDACIÓN CORREGIDA - USAR SNAKE_CASE COMO EN EL HTML
    $request->validate([
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'motivo_consulta' => 'nullable|string',
        
        // ✅ DIAGNÓSTICO PRINCIPAL - REQUIRED
        'idDiagnostico' => 'required|string',
        'tipo_diagnostico' => 'required|string|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        // ✅ DIAGNÓSTICOS ADICIONALES - SNAKE_CASE (id_diagnostico)
        'diagnosticos_adicionales' => 'nullable|array',
        'diagnosticos_adicionales.*.id_diagnostico' => 'required_with:diagnosticos_adicionales|string',
        'diagnosticos_adicionales.*.tipo_diagnostico' => 'required_with:diagnosticos_adicionales|string|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        // ✅ REMISIONES - SNAKE_CASE (id_remision y observacion)
        'remisiones' => 'nullable|array',
        'remisiones.*.id_remision' => 'required_with:remisiones|string',
        'remisiones.*.observacion' => 'nullable|string|max:500',
        
        // ✅ OTROS CAMPOS
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
            'paciente_uuid' => $request->paciente_uuid,
            'tiene_diagnostico_principal' => !empty($request->idDiagnostico),
            'diagnosticos_adicionales_count' => $request->diagnosticos_adicionales ? count($request->diagnosticos_adicionales) : 0,
            'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
        ]);

        // ✅ LOG DE DATOS RECIBIDOS
        \Log::info('📋 Datos recibidos en storeFisioterapia', [
            'idDiagnostico' => $request->idDiagnostico,
            'tipo_diagnostico' => $request->tipo_diagnostico,
            'diagnosticos_adicionales' => $request->diagnosticos_adicionales,
            'remisiones' => $request->remisiones,
        ]);

        // ✅ CREAR HISTORIA - SOLO CAMPOS DE FISIOTERAPIA
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // ✅ CAMPOS BÁSICOS
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'acompanante' => $request->acompanante,
            'acu_parentesco' => $request->acu_parentesco,
            'acu_telefono' => $request->acu_telefono,
            'motivo_consulta' => $request->motivo_consulta ?? '',
            
            // ✅ MEDIDAS ANTROPOMÉTRICAS
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

        // ✅ CREAR COMPLEMENTARIA CON CAMPOS DE FISIOTERAPIA
        \App\Models\HistoriaClinicaComplementaria::create([
            'uuid' => Str::uuid(),
            'historia_clinica_id' => $historia->id,
            
            // ✅ EVALUACIONES ESPECÍFICAS DE FISIOTERAPIA
            'actitud' => $request->actitud,
            'evaluacion_d' => $request->evaluacion_d,
            'evaluacion_p' => $request->evaluacion_p,
            'estado' => $request->estado,
            'evaluacion_dolor' => $request->evaluacion_dolor,
            'evaluacion_os' => $request->evaluacion_os,
            'evaluacion_neu' => $request->evaluacion_neu,
            'comitante' => $request->comitante,
            
            // ✅ PLAN DE TRATAMIENTO
            'plan_seguir' => $request->plan_seguir,
        ]);

        \Log::info('✅ Tabla complementaria creada');

        // ✅ PROCESAR DIAGNÓSTICO PRINCIPAL
        $diagnosticosProcesados = [];
        
        if ($request->idDiagnostico && !empty($request->idDiagnostico)) {
            \Log::info('🔍 Procesando diagnóstico principal FISIOTERAPIA', [
                'idDiagnostico' => $request->idDiagnostico,
                'tipo_diagnostico' => $request->tipo_diagnostico
            ]);
            
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
                \Log::info('✅ Diagnóstico principal FISIOTERAPIA guardado', [
                    'diagnostico_id' => $diagnostico->id,
                    'codigo' => $diagnostico->codigo,
                    'nombre' => $diagnostico->nombre
                ]);
            } else {
                \Log::error('❌ Diagnóstico principal NO ENCONTRADO', [
                    'idDiagnostico' => $request->idDiagnostico
                ]);
            }
        } else {
            \Log::warning('⚠️ No se recibió diagnóstico principal', [
                'idDiagnostico' => $request->idDiagnostico,
                'is_empty' => empty($request->idDiagnostico)
            ]);
        }

        // ✅ PROCESAR DIAGNÓSTICOS ADICIONALES - CORREGIDO CON SNAKE_CASE
        if ($request->has('diagnosticos_adicionales') && is_array($request->diagnosticos_adicionales)) {
            \Log::info('🔍 Procesando diagnósticos adicionales FISIOTERAPIA', [
                'count' => count($request->diagnosticos_adicionales),
                'data' => $request->diagnosticos_adicionales
            ]);
            
            foreach ($request->diagnosticos_adicionales as $index => $diag) {
                \Log::info("🔍 Procesando diagnóstico adicional #{$index}", [
                    'diag' => $diag,
                    'tiene_id_diagnostico' => !empty($diag['id_diagnostico']) // ✅ CAMBIO AQUÍ
                ]);
                
                // ✅ USAR id_diagnostico (SNAKE_CASE) EN LUGAR DE idDiagnostico
                if (!empty($diag['id_diagnostico'])) {
                    $diagnostico = \App\Models\Diagnostico::where('uuid', $diag['id_diagnostico'])
                        ->orWhere('id', $diag['id_diagnostico'])
                        ->first();
                    
                    if ($diagnostico && !in_array($diagnostico->id, $diagnosticosProcesados)) {
                        \App\Models\HistoriaDiagnostico::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'diagnostico_id' => $diagnostico->id,
                            'tipo' => 'SECUNDARIO',
                            'tipo_diagnostico' => $diag['tipo_diagnostico'] ?? 'IMPRESION_DIAGNOSTICA',
                        ]);
                        $diagnosticosProcesados[] = $diagnostico->id;
                        \Log::info('✅ Diagnóstico adicional FISIOTERAPIA guardado', [
                            'index' => $index,
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    } else {
                        \Log::warning('⚠️ Diagnóstico adicional no encontrado o duplicado', [
                            'index' => $index,
                            'id_diagnostico' => $diag['id_diagnostico'], // ✅ CAMBIO AQUÍ
                            'encontrado' => $diagnostico ? 'SI' : 'NO',
                            'duplicado' => $diagnostico ? in_array($diagnostico->id, $diagnosticosProcesados) : false
                        ]);
                    }
                } else {
                    \Log::warning('⚠️ Diagnóstico adicional sin ID', [
                        'index' => $index,
                        'diag' => $diag
                    ]);
                }
            }
        } else {
            \Log::info('ℹ️ No hay diagnósticos adicionales para procesar');
        }

        // ✅ PROCESAR REMISIONES - CORREGIDO CON SNAKE_CASE
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones FISIOTERAPIA', [
                'count' => count($request->remisiones),
                'data' => $request->remisiones
            ]);
            
            foreach ($request->remisiones as $index => $rem) {
                \Log::info("🔍 Procesando remisión #{$index}", [
                    'rem' => $rem,
                    'tiene_id_remision' => !empty($rem['id_remision']) // ✅ CAMBIO AQUÍ
                ]);
                
                // ✅ USAR id_remision (SNAKE_CASE) EN LUGAR DE idRemision
                $remisionId = $rem['id_remision'] ?? null;
                
                if (!empty($remisionId)) {
                    $remision = \App\Models\Remision::where('uuid', $remisionId)
                        ->orWhere('id', $remisionId)
                        ->first();
                    
                    if ($remision) {
                        \App\Models\HistoriaRemision::create([
                            'uuid' => Str::uuid(),
                            'historia_clinica_id' => $historia->id,
                            'remision_id' => $remision->id,
                            'observacion' => $rem['observacion'] ?? null, // ✅ CAMBIO AQUÍ (observacion, no remObservacion)
                        ]);
                        \Log::info('✅ Remisión FISIOTERAPIA guardada', [
                            'index' => $index,
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    } else {
                        \Log::error('❌ Remisión NO ENCONTRADA', [
                            'index' => $index,
                            'id_remision' => $remisionId // ✅ CAMBIO AQUÍ
                        ]);
                    }
                } else {
                    \Log::warning('⚠️ Remisión sin ID', [
                        'index' => $index,
                        'rem' => $rem
                    ]);
                }
            }
        } else {
            \Log::info('ℹ️ No hay remisiones para procesar');
        }

        DB::commit();

        // ✅ CARGAR RELACIONES
        $historia->load([
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'historiaRemisiones.remision',
            'complementaria'
        ]);

        \Log::info('✅ Historia de fisioterapia guardada exitosamente', [
            'historia_uuid' => $historia->uuid,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count()
        ]);

        // ✅ RESPUESTA LIMPIA SIN REDIRECT
        return response()->json([
            'success' => true,
            'message' => 'Historia clínica de fisioterapia guardada exitosamente',
            'data' => [
                'historia' => $historia,
                'uuid' => $historia->uuid,
                'cita_uuid' => $cita->uuid,
                'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
                'remisiones_count' => $historia->historiaRemisiones->count(),
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de fisioterapia', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'trace' => $e->getTraceAsString(),
            'request_all' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de fisioterapia',
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
