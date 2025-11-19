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
 
   public function index(Request $request)
    {
        try {
            Log::info('📋 API GET Request - Historias Clínicas', [
                'filters' => $request->all()
            ]);

            // ✅ JOIN con citas para poder ordenar por su fecha
            $query = HistoriaClinica::query()
                ->join('citas', 'historias_clinicas.cita_id', '=', 'citas.id')
                ->select('historias_clinicas.*', 'citas.fecha as cita_fecha')
                ->with([
                    'sede',
                    'cita',
                    'cita.paciente',
                    'cita.agenda.usuario',
                    'cita.agenda.usuarioMedico',
                    'cita.agenda.proceso',
                    
                    // ✅ AGREGAR ESTAS 2 LÍNEAS PARA TIPO DE CONSULTA
                    'cita.cupsContratado',
                    'cita.cupsContratado.categoriaCups',
                    
                    'historiaDiagnosticos.diagnostico',
                    'historiaMedicamentos.medicamento',
                    'historiaRemisiones.remision',
                    'historiaCups.cups',
                    'complementaria'
                ]);

            // Filtros
            if ($request->filled('documento')) {
                $query->whereHas('cita.paciente', function ($q) use ($request) {
                    $q->where('documento', $request->documento);
                });
            }

            if ($request->has('fecha_desde')) {
                $query->whereDate('citas.fecha', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('citas.fecha', '<=', $request->fecha_hasta);
            }

            if ($request->filled('especialidad')) {
                $query->whereHas('cita.agenda.proceso', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->especialidad . '%');
                });
            }

            // ✅ AGREGAR FILTRO POR TIPO DE CONSULTA
            if ($request->filled('tipo_consulta')) {
                $tipoConsulta = strtoupper($request->tipo_consulta);
                
                $query->whereHas('cita.cupsContratado.categoriaCups', function ($q) use ($tipoConsulta) {
                    if ($tipoConsulta === 'PRIMERA VEZ' || $tipoConsulta === 'PRIMERA_VEZ') {
                        $q->where('id', 1);
                    } elseif ($tipoConsulta === 'CONTROL') {
                        $q->where('id', 2);
                    }
                });
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $perPage = max(5, min(100, (int) $perPage));
            
            $historias = $query->orderBy('citas.fecha', 'desc')
                            ->paginate($perPage);

            // ✅ TRANSFORMAR DATOS CON ESPECIALIDAD Y TIPO DE CONSULTA
            $historiasTransformadas = $historias->getCollection()->map(function ($historia) {
                // ✅ OBTENER ESPECIALIDAD DESDE AGENDA → PROCESO
                $especialidad = 'N/A';
                if ($historia->cita && $historia->cita->agenda && $historia->cita->agenda->proceso) {
                    $especialidad = $historia->cita->agenda->proceso->nombre ?? 'N/A';
                }

                // ✅ OBTENER TIPO DE CONSULTA DESDE CUPS CONTRATADO → CATEGORÍA CUPS
                $tipoConsulta = $this->obtenerTipoConsulta($historia);

                return [
                    'uuid' => $historia->uuid,
                    'cita_id' => $historia->cita_id,
                    'sede_id' => $historia->sede_id,
                    'especialidad' => $especialidad,
                    
                    // ✅ AGREGAR TIPO DE CONSULTA
                    'tipo_consulta' => $tipoConsulta,
                    
                    'diagnostico_principal' => $historia->diagnostico_principal,
                    'motivo_consulta' => $historia->motivo_consulta,
                    'enfermedad_actual' => $historia->enfermedad_actual,
                    'created_at' => $historia->created_at,
                    'updated_at' => $historia->updated_at,
                    
                    // ✅ CITA CON FECHA
                    'cita' => [
                        'uuid' => $historia->cita->uuid ?? null,
                        'fecha' => $historia->cita->fecha ?? null,
                        'hora' => $historia->cita->hora ?? null,
                        'estado' => $historia->cita->estado ?? null,
                        
                        // ✅ PACIENTE
                        'paciente' => $historia->cita && $historia->cita->paciente ? [
                            'uuid' => $historia->cita->paciente->uuid,
                            'nombre_completo' => $historia->cita->paciente->nombre_completo ?? 
                                                trim(($historia->cita->paciente->primer_nombre ?? '') . ' ' . 
                                                    ($historia->cita->paciente->segundo_nombre ?? '') . ' ' . 
                                                    ($historia->cita->paciente->primer_apellido ?? '') . ' ' . 
                                                    ($historia->cita->paciente->segundo_apellido ?? '')),
                            'tipo_documento' => $historia->cita->paciente->tipo_documento ?? 'CC',
                            'documento' => $historia->cita->paciente->documento ?? 'N/A',
                            'fecha_nacimiento' => $historia->cita->paciente->fecha_nacimiento ?? null,
                            'sexo' => $historia->cita->paciente->sexo ?? null,
                        ] : null,
                        
                        // ✅ AGENDA CON PROFESIONAL Y PROCESO
                        'agenda' => $historia->cita && $historia->cita->agenda ? [
                            'uuid' => $historia->cita->agenda->uuid,
                            
                            // ✅ PROCESO (ESPECIALIDAD)
                            'proceso' => $historia->cita->agenda->proceso ? [
                                'uuid' => $historia->cita->agenda->proceso->uuid,
                                'nombre' => $historia->cita->agenda->proceso->nombre ?? 'N/A',
                            ] : null,
                            
                            // ✅ PROFESIONAL
                            'usuario_medico' => $historia->cita->agenda->usuarioMedico ? [
                                'uuid' => $historia->cita->agenda->usuarioMedico->uuid,
                                'nombre_completo' => $historia->cita->agenda->usuarioMedico->nombre_completo ?? 
                                                    trim(($historia->cita->agenda->usuarioMedico->primer_nombre ?? '') . ' ' . 
                                                        ($historia->cita->agenda->usuarioMedico->primer_apellido ?? '')),
                            ] : ($historia->cita->agenda->usuario ? [
                                'uuid' => $historia->cita->agenda->usuario->uuid,
                                'nombre_completo' => $historia->cita->agenda->usuario->nombre_completo ?? 
                                                    trim(($historia->cita->agenda->usuario->primer_nombre ?? '') . ' ' . 
                                                        ($historia->cita->agenda->usuario->primer_apellido ?? '')),
                            ] : null),
                        ] : null,
                    ],
                    
                    // ✅ SEDE
                    'sede' => $historia->sede ? [
                        'uuid' => $historia->sede->uuid,
                        'nombre' => $historia->sede->nombre ?? 'N/A',
                    ] : null,
                ];
            });

            // ✅ REEMPLAZAR LA COLECCIÓN TRANSFORMADA
            $historias->setCollection($historiasTransformadas);

            return response()->json([
                'success' => true,
                'data' => $historias,
                'message' => 'Historias clínicas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error en HistoriaClinicaController', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historias clínicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO AUXILIAR PARA OBTENER TIPO DE CONSULTA
     * Navega: Historia → Cita → CupsContratado → CategoriaCups
     */
   /**
 * ✅ OBTENER TIPO DE CONSULTA CON DEBUG COMPLETO
 */
private function obtenerTipoConsulta($historia): ?string
{
    try {
        Log::info('🔍 DEBUG - Iniciando obtenerTipoConsulta', [
            'historia_uuid' => $historia->uuid ?? null,
            'historia_id' => $historia->id ?? null
        ]);

        // ✅ PASO 1: Verificar si existe la cita
        if (!$historia->cita) {
            Log::warning('⚠️ Historia sin cita', [
                'historia_uuid' => $historia->uuid ?? null
            ]);
            return null;
        }

        Log::info('✅ Cita encontrada', [
            'cita_id' => $historia->cita->id ?? null,
            'cita_uuid' => $historia->cita->uuid ?? null,
            'cups_contratado_id' => $historia->cita->cups_contratado_id ?? 'NULL'
        ]);

        // ✅ PASO 2: Verificar si existe cups_contratado_id
        if (!isset($historia->cita->cups_contratado_id) || empty($historia->cita->cups_contratado_id)) {
            Log::warning('⚠️ Cita sin cups_contratado_id', [
                'cita_id' => $historia->cita->id ?? null,
                'cups_contratado_id' => $historia->cita->cups_contratado_id ?? 'NULL'
            ]);
            return null;
        }

        // ✅ PASO 3: Verificar si la relación cupsContratado está cargada
        if (!$historia->cita->cupsContratado) {
            Log::warning('⚠️ Relación cupsContratado NO cargada', [
                'cita_id' => $historia->cita->id ?? null,
                'cups_contratado_id' => $historia->cita->cups_contratado_id
            ]);
            return null;
        }

        Log::info('✅ CupsContratado encontrado', [
            'cups_contratado_id' => $historia->cita->cupsContratado->id ?? null,
            'categoria_cups_id' => $historia->cita->cupsContratado->categoria_cups_id ?? 'NULL'
        ]);

        // ✅ PASO 4: Verificar si existe categoria_cups_id
        if (!isset($historia->cita->cupsContratado->categoria_cups_id) || 
            empty($historia->cita->cupsContratado->categoria_cups_id)) {
            Log::warning('⚠️ CupsContratado sin categoria_cups_id', [
                'cups_contratado_id' => $historia->cita->cupsContratado->id ?? null,
                'categoria_cups_id' => $historia->cita->cupsContratado->categoria_cups_id ?? 'NULL'
            ]);
            return null;
        }

        // ✅ PASO 5: Verificar si la relación categoriaCups está cargada
        if (!$historia->cita->cupsContratado->categoriaCups) {
            Log::warning('⚠️ Relación categoriaCups NO cargada', [
                'cups_contratado_id' => $historia->cita->cupsContratado->id ?? null,
                'categoria_cups_id' => $historia->cita->cupsContratado->categoria_cups_id
            ]);
            return null;
        }

        $categoriaCups = $historia->cita->cupsContratado->categoriaCups;

        Log::info('✅ CategoriaCups encontrada', [
            'categoria_id' => $categoriaCups->id ?? null,
            'categoria_nombre' => $categoriaCups->nombre ?? 'NULL'
        ]);

        // ✅ PASO 6: Determinar tipo según el ID de la categoría
        $tipoConsulta = match ((int)$categoriaCups->id) {
            1 => 'PRIMERA VEZ',
            2 => 'CONTROL',
            default => $categoriaCups->nombre ?? null
        };

        Log::info('✅ Tipo consulta determinado', [
            'historia_uuid' => $historia->uuid ?? null,
            'categoria_id' => $categoriaCups->id,
            'tipo_consulta' => $tipoConsulta
        ]);

        return $tipoConsulta;
        
    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo tipo consulta', [
            'historia_uuid' => $historia->uuid ?? null,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'trace' => $e->getTraceAsString()
        ]);
        return null;
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

        if ($especialidad === 'MEDICINA INTERNA' || $especialidad === 'INTERNISTA') {
            DB::rollBack();
            return $this->storeInternista($request, $cita);
        }

         // ✅ SI ES FISIOTERAPIA, USAR MÉTODO ESPECÍFICO
        if ($especialidad === 'NEFROLOGIA') {
            DB::rollBack();
            return $this->storeNefrologia($request, $cita);
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
 * ✅ PREPARAR DATOS SEGÚN TIPO DE CONSULTA - VERSIÓN CORREGIDA CON valorONull()
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

    // ✅ CAMPOS COMUNES A PRIMERA VEZ Y CONTROL - TODOS CON valorONull()
    $camposComunes = [
        'finalidad' => $request->finalidad ?? 'CONSULTA',
        'causa_externa' => $this->valorONull($request->causa_externa),
        'acompanante' => $this->valorONull($request->acompanante),
        'acu_parentesco' => $this->valorONull($request->acu_parentesco),
        'acu_telefono' => $this->valorONull($request->acu_telefono),
        
        // Medidas antropométricas
        'peso' => $this->valorONull($request->peso),
        'talla' => $this->valorONull($request->talla),
        'imc' => $this->valorONull($request->imc),
        'clasificacion' => $this->valorONull($request->clasificacion),
        'perimetro_abdominal' => $this->valorONull($request->perimetro_abdominal),
        'obs_perimetro_abdominal' => $this->valorONull($request->obs_perimetro_abdominal),
        
        // Test de Morisky
        'olvida_tomar_medicamentos' => $this->valorONull($request->olvida_tomar_medicamentos) ?? 'NO',
        'toma_medicamentos_hora_indicada' => $this->valorONull($request->toma_medicamentos_hora_indicada) ?? 'SI',
        'cuando_esta_bien_deja_tomar_medicamentos' => $this->valorONull($request->cuando_esta_bien_deja_tomar_medicamentos) ?? 'NO',
        'siente_mal_deja_tomarlos' => $this->valorONull($request->siente_mal_deja_tomarlos) ?? 'NO',
        'valoracion_psicologia' => $this->valorONull($request->valoracion_psicologia) ?? 'NO',
        'adherente' => $this->valorONull($request->adherente),
        
        // Revisión por sistemas
        'general' => $this->valorONull($request->general),
        'cabeza' => $this->valorONull($request->cabeza) ?? 'NORMAL',
        'respiratorio' => $this->valorONull($request->respiratorio),
        'cardiovascular' => $this->valorONull($request->cardiovascular) ?? 'NORMAL',
        'gastrointestinal' => $this->valorONull($request->gastrointestinal) ?? 'NORMAL',
        'osteoatromuscular' => $this->valorONull($request->osteoatromuscular) ?? 'NORMAL',
        'snc' => $this->valorONull($request->snc) ?? 'NORMAL',
        
        // Signos vitales
        'presion_arterial_sistolica_sentado_pie' => $this->valorONull($request->presion_arterial_sistolica_sentado_pie),
        'presion_arterial_distolica_sentado_pie' => $this->valorONull($request->presion_arterial_distolica_sentado_pie),
        'frecuencia_cardiaca' => $this->valorONull($request->frecuencia_cardiaca),
        'frecuencia_respiratoria' => $this->valorONull($request->frecuencia_respiratoria),
        
        // Examen físico
        'ef_cabeza' => $this->valorONull($request->ef_cabeza) ?? 'NORMAL',
        'agudeza_visual' => $this->valorONull($request->agudeza_visual) ?? 'NORMAL',
        'oidos' => $this->valorONull($request->oidos) ?? 'NORMAL',
        'nariz_senos_paranasales' => $this->valorONull($request->nariz_senos_paranasales) ?? 'NORMAL',
        'cavidad_oral' => $this->valorONull($request->cavidad_oral) ?? 'NORMAL',
        'cuello' => $this->valorONull($request->cuello) ?? 'NORMAL',
        'cardio_respiratorio' => $this->valorONull($request->cardio_respiratorio) ?? 'NORMAL',
        'mamas' => $this->valorONull($request->mamas) ?? 'NORMAL',
        'genito_urinario' => $this->valorONull($request->genito_urinario) ?? 'NORMAL',
        'musculo_esqueletico' => $this->valorONull($request->musculo_esqueletico) ?? 'NORMAL',
        'piel_anexos_pulsos' => $this->valorONull($request->piel_anexos_pulsos) ?? 'NORMAL',
        'inspeccion_sensibilidad_pies' => $this->valorONull($request->inspeccion_sensibilidad_pies) ?? 'NORMAL',
        'sistema_nervioso' => $this->valorONull($request->sistema_nervioso) ?? 'NORMAL',
        'capacidad_cognitiva_orientacion' => $this->valorONull($request->capacidad_cognitiva_orientacion) ?? 'NORMAL',
        'reflejo_aquiliar' => $this->valorONull($request->reflejo_aquiliar) ?? 'NORMAL',
        'reflejo_patelar' => $this->valorONull($request->reflejo_patelar) ?? 'NORMAL',
        
        // Observaciones examen físico
        'obs_cabeza' => $this->valorONull($request->obs_cabeza),
        'obs_agudeza_visual' => $this->valorONull($request->obs_agudeza_visual),
        'obs_cuello' => $this->valorONull($request->obs_cuello),
        'obs_torax' => $this->valorONull($request->obs_torax),
        'obs_mamas' => $this->valorONull($request->obs_mamas),
        'obs_abdomen' => $this->valorONull($request->obs_abdomen),
        'obs_genito_urinario' => $this->valorONull($request->obs_genito_urinario),
        'obs_extremidades' => $this->valorONull($request->obs_extremidades),
        'obs_piel_anexos_pulsos' => $this->valorONull($request->obs_piel_anexos_pulsos),
        'obs_sistema_nervioso' => $this->valorONull($request->obs_sistema_nervioso),
        'obs_orientacion' => $this->valorONull($request->obs_orientacion),
        'hallazgo_positivo_examen_fisico' => $this->valorONull($request->hallazgo_positivo_examen_fisico),
        
        // Factores de riesgo
        'dislipidemia' => $this->valorONull($request->dislipidemia) ?? 'NO',
        'lesion_organo_blanco' => $this->valorONull($request->lesion_organo_blanco) ?? 'NO',
        'descripcion_lesion_organo_blanco' => $this->valorONull($request->descripcion_lesion_organo_blanco),
        
        // Exámenes complementarios
        'fex_es' => $this->valorONull($request->fex_es),
        'electrocardiograma' => $this->valorONull($request->electrocardiograma),
        'fex_es1' => $this->valorONull($request->fex_es1),
        'ecocardiograma' => $this->valorONull($request->ecocardiograma),
        'fex_es2' => $this->valorONull($request->fex_es2),
        'ecografia_renal' => $this->valorONull($request->ecografia_renal),
        
        // ✅✅✅ CLASIFICACIONES - AHORA CON valorONull ✅✅✅
        'clasificacion_estado_metabolico' => $this->valorONull($request->clasificacion_estado_metabolico),
        'clasificacion_hta' => $this->valorONull($request->clasificacion_hta),
        'clasificacion_dm' => $this->valorONull($request->clasificacion_dm),
        'clasificacion_rcv' => $this->valorONull($request->clasificacion_rcv),
        'clasificacion_erc_estado' => $this->valorONull($request->clasificacion_erc_estado),
        'clasificacion_erc_categoria_ambulatoria_persistente' => $this->valorONull($request->clasificacion_erc_categoria_ambulatoria_persistente),
        
        // ✅✅✅ TASAS DE FILTRACIÓN - AHORA CON valorONull ✅✅✅
        'tasa_filtracion_glomerular_ckd_epi' => $this->valorONull($request->tasa_filtracion_glomerular_ckd_epi),
        'tasa_filtracion_glomerular_gockcroft_gault' => $this->valorONull($request->tasa_filtracion_glomerular_gockcroft_gault),
        
        // ✅✅✅ ANTECEDENTES PERSONALES - AHORA CON valorONull ✅✅✅
        'hipertension_arterial_personal' => $this->valorONull($request->hipertension_arterial_personal) ?? 'NO',
        'obs_personal_hipertension_arterial' => $this->valorONull($request->obs_personal_hipertension_arterial),
        'diabetes_mellitus_personal' => $this->valorONull($request->diabetes_mellitus_personal) ?? 'NO',
        'obs_personal_mellitus' => $this->valorONull($request->obs_personal_mellitus),
        
        // ✅✅✅ EDUCACIÓN EN SALUD - AHORA CON valorONull ✅✅✅
        'alimentacion' => $this->valorONull($request->alimentacion) ?? 'NO',
        'disminucion_consumo_sal_azucar' => $this->valorONull($request->disminucion_consumo_sal_azucar) ?? 'NO',
        'fomento_actividad_fisica' => $this->valorONull($request->fomento_actividad_fisica) ?? 'NO',
        'importancia_adherencia_tratamiento' => $this->valorONull($request->importancia_adherencia_tratamiento) ?? 'NO',
        'consumo_frutas_verduras' => $this->valorONull($request->consumo_frutas_verduras) ?? 'NO',
        'manejo_estres' => $this->valorONull($request->manejo_estres) ?? 'NO',
        'disminucion_consumo_cigarrillo' => $this->valorONull($request->disminucion_consumo_cigarrillo) ?? 'NO',
        'disminucion_peso' => $this->valorONull($request->disminucion_peso) ?? 'NO',
        
        'observaciones_generales' => $this->valorONull($request->observaciones_generales),
    ];

    // ✅ AGREGAR CAMPOS COMUNES
    $datos = array_merge($datos, $camposComunes);

    // ✅ CAMPOS EXCLUSIVOS DE PRIMERA VEZ - TODOS CON valorONull()
    if ($request->tipo_consulta === 'PRIMERA VEZ') {
        $camposPrimeraVez = [
            // Discapacidades
            'discapacidad_fisica' => $this->valorONull($request->discapacidad_fisica) ?? 'NO',
            'discapacidad_visual' => $this->valorONull($request->discapacidad_visual) ?? 'NO',
            'discapacidad_mental' => $this->valorONull($request->discapacidad_mental) ?? 'NO',
            'discapacidad_auditiva' => $this->valorONull($request->discapacidad_auditiva) ?? 'NO',
            'discapacidad_intelectual' => $this->valorONull($request->discapacidad_intelectual) ?? 'NO',
            
            // Drogodependencia
            'drogo_dependiente' => $this->valorONull($request->drogo_dependiente) ?? 'NO',
            'drogo_dependiente_cual' => $this->valorONull($request->drogo_dependiente_cual),
            
            // Antecedentes Familiares
            'hipertension_arterial' => $this->valorONull($request->hipertension_arterial) ?? 'NO',
            'parentesco_hipertension' => $this->valorONull($request->parentesco_hipertension),
            'diabetes_mellitus' => $this->valorONull($request->diabetes_mellitus) ?? 'NO',
            'parentesco_mellitus' => $this->valorONull($request->parentesco_mellitus),
            'artritis' => $this->valorONull($request->artritis) ?? 'NO',
            'parentesco_artritis' => $this->valorONull($request->parentesco_artritis),
            'enfermedad_cardiovascular' => $this->valorONull($request->enfermedad_cardiovascular) ?? 'NO',
            'parentesco_cardiovascular' => $this->valorONull($request->parentesco_cardiovascular),
            'antecedente_metabolico' => $this->valorONull($request->antecedente_metabolico) ?? 'NO',
            'parentesco_metabolico' => $this->valorONull($request->parentesco_metabolico),
            'cancer_mama_estomago_prostata_colon' => $this->valorONull($request->cancer_mama_estomago_prostata_colon) ?? 'NO',
            'parentesco_cancer' => $this->valorONull($request->parentesco_cancer),
            'leucemia' => $this->valorONull($request->leucemia) ?? 'NO',
            'parentesco_leucemia' => $this->valorONull($request->parentesco_leucemia),
            'vih' => $this->valorONull($request->vih) ?? 'NO',
            'parentesco_vih' => $this->valorONull($request->parentesco_vih),
            'otro' => $this->valorONull($request->otro) ?? 'NO',
            'parentesco_otro' => $this->valorONull($request->parentesco_otro),
            
            // Antecedentes Personales Adicionales
            'enfermedad_cardiovascular_personal' => $this->valorONull($request->enfermedad_cardiovascular_personal) ?? 'NO',
            'obs_personal_enfermedad_cardiovascular' => $this->valorONull($request->obs_personal_enfermedad_cardiovascular),
            'arterial_periferica_personal' => $this->valorONull($request->arterial_periferica_personal) ?? 'NO',
            'obs_personal_arterial_periferica' => $this->valorONull($request->obs_personal_arterial_periferica),
            'carotidea_personal' => $this->valorONull($request->carotidea_personal) ?? 'NO',
            'obs_personal_carotidea' => $this->valorONull($request->obs_personal_carotidea),
            'aneurisma_aorta_personal' => $this->valorONull($request->aneurisma_aorta_personal) ?? 'NO',
            'obs_personal_aneurisma_aorta' => $this->valorONull($request->obs_personal_aneurisma_aorta),
            'sindrome_coronario_agudo_angina_personal' => $this->valorONull($request->sindrome_coronario_agudo_angina_personal) ?? 'NO',
            'obs_personal_sindrome_coronario' => $this->valorONull($request->obs_personal_sindrome_coronario),
            'artritis_personal' => $this->valorONull($request->artritis_personal) ?? 'NO',
            'obs_personal_artritis' => $this->valorONull($request->obs_personal_artritis),
            'iam_personal' => $this->valorONull($request->iam_personal) ?? 'NO',
            'obs_personal_iam' => $this->valorONull($request->obs_personal_iam),
            'revascul_coronaria_personal' => $this->valorONull($request->revascul_coronaria_personal) ?? 'NO',
            'obs_personal_revascul_coronaria' => $this->valorONull($request->obs_personal_revascul_coronaria),
            'insuficiencia_cardiaca_personal' => $this->valorONull($request->insuficiencia_cardiaca_personal) ?? 'NO',
            'obs_personal_insuficiencia_cardiaca' => $this->valorONull($request->obs_personal_insuficiencia_cardiaca),
            'amputacion_pie_diabetico_personal' => $this->valorONull($request->amputacion_pie_diabetico_personal) ?? 'NO',
            'obs_personal_amputacion_pie_diabetico' => $this->valorONull($request->obs_personal_amputacion_pie_diabetico),
            'enfermedad_pulmonar_personal' => $this->valorONull($request->enfermedad_pulmonar_personal) ?? 'NO',
            'obs_personal_enfermedad_pulmonar' => $this->valorONull($request->obs_personal_enfermedad_pulmonar),
            'victima_maltrato_personal' => $this->valorONull($request->victima_maltrato_personal) ?? 'NO',
            'obs_personal_maltrato_personal' => $this->valorONull($request->obs_personal_maltrato_personal),
            'antecedentes_quirurgicos' => $this->valorONull($request->antecedentes_quirurgicos) ?? 'NO',
            'obs_personal_antecedentes_quirurgicos' => $this->valorONull($request->obs_personal_antecedentes_quirurgicos),
            'acontosis_personal' => $this->valorONull($request->acontosis_personal) ?? 'NO',
            'obs_personal_acontosis' => $this->valorONull($request->obs_personal_acontosis),
            'otro_personal' => $this->valorONull($request->otro_personal) ?? 'NO',
            'obs_personal_otro' => $this->valorONull($request->obs_personal_otro),
            
            // Revisión por sistemas adicional
            'orl' => $this->valorONull($request->orl) ?? 'NORMAL',
            'revision_sistemas' => $this->valorONull($request->revision_sistemas),
            
            // Examen físico adicional
            'presion_arterial_sistolica_acostado' => $this->valorONull($request->presion_arterial_sistolica_acostado),
            'presion_arterial_distolica_acostado' => $this->valorONull($request->presion_arterial_distolica_acostado),
            'fundoscopia' => $this->valorONull($request->fundoscopia) ?? 'NORMAL',
            'obs_fundoscopia' => $this->valorONull($request->obs_fundoscopia),
            'torax' => $this->valorONull($request->torax) ?? 'NORMAL',
            'abdomen' => $this->valorONull($request->abdomen) ?? 'NORMAL',
            'extremidades' => $this->valorONull($request->extremidades) ?? 'NORMAL',
            'capacidad_cognitiva' => $this->valorONull($request->capacidad_cognitiva) ?? 'NORMAL',
            'obs_capacidad_cognitiva' => $this->valorONull($request->obs_capacidad_cognitiva),
            'orientacion' => $this->valorONull($request->orientacion) ?? 'NORMAL',
            'obs_reflejo_aquiliar' => $this->valorONull($request->obs_reflejo_aquiliar),
            'obs_reflejo_patelar' => $this->valorONull($request->obs_reflejo_patelar),
            
            // Factores de riesgo adicionales
            'tabaquismo' => $this->valorONull($request->tabaquismo) ?? 'NO',
            'obs_tabaquismo' => $this->valorONull($request->obs_tabaquismo),
            'obs_dislipidemia' => $this->valorONull($request->obs_dislipidemia),
            'menor_cierta_edad' => $this->valorONull($request->menor_cierta_edad) ?? 'NO',
            'obs_menor_cierta_edad' => $this->valorONull($request->obs_menor_cierta_edad),
            'condicion_clinica_asociada' => $this->valorONull($request->condicion_clinica_asociada) ?? 'NO',
            'obs_condicion_clinica_asociada' => $this->valorONull($request->obs_condicion_clinica_asociada),
            'obs_lesion_organo_blanco' => $this->valorONull($request->obs_lesion_organo_blanco),
            
            // Otros campos de primera vez
            'insulina_requiriente' => $this->valorONull($request->insulina_requiriente),
            'recibe_tratamiento_alternativo' => $this->valorONull($request->recibe_tratamiento_alternativo) ?? 'NO',
            'recibe_tratamiento_con_plantas_medicinales' => $this->valorONull($request->recibe_tratamiento_con_plantas_medicinales) ?? 'NO',
            'recibe_ritual_medicina_tradicional' => $this->valorONull($request->recibe_ritual_medicina_tradicional) ?? 'NO',
            'numero_frutas_diarias' => $this->valorONull($request->numero_frutas_diarias) ?? 0,
            'elevado_consumo_grasa_saturada' => $this->valorONull($request->elevado_consumo_grasa_saturada) ?? 'NO',
            'adiciona_sal_despues_preparar_comida' => $this->valorONull($request->adiciona_sal_despues_preparar_comida) ?? 'NO',
            
            // Reformulación
            'razon_reformulacion' => $this->valorONull($request->razon_reformulacion),
            'motivo_reformulacion' => $this->valorONull($request->motivo_reformulacion),
            'reformulacion_quien_reclama' => $this->valorONull($request->reformulacion_quien_reclama),
            'reformulacion_nombre_reclama' => $this->valorONull($request->reformulacion_nombre_reclama),
            'adicional' => $this->valorONull($request->adicional),
        ];
        
        $datos = array_merge($datos, $camposPrimeraVez);
    }

    return $datos;
}

/**
 * ✅ MÉTODO AUXILIAR - RETORNA NULL SI EL VALOR ESTÁ VACÍO
 */
private function valorONull($valor)
{
    // Lista de valores que se consideran "vacíos"
    $valoresVacios = [null, '', 'null', 'undefined', 'NaN', 'false'];
    
    // Si está en la lista o es solo espacios
    if (in_array($valor, $valoresVacios, true) || 
        (is_string($valor) && trim($valor) === '')) {
        return null;
    }
    
    // Si es 0 o "0", lo mantiene
    if ($valor === 0 || $valor === '0') {
        return $valor;
    }
    
    return $valor;
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
        
        // ✅ CUPS (AMBOS TIPOS)
        'cups' => 'nullable|array',
        'cups.*.cups_id' => 'required_with:cups|string',
        'cups.*.observacion' => 'nullable|string',
        
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
            'cups_count' => $request->cups ? count($request->cups) : 0,
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
            'tabaquismo' => $request->tabaquismo,
            
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

        // ✅ PROCESAR CUPS (AMBOS TIPOS)
        if ($request->has('cups') && is_array($request->cups)) {
            \Log::info('🔍 Procesando CUPS NUTRICIONISTA', [
                'count' => count($request->cups)
            ]);
            
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
                        \Log::info('✅ CUPS NUTRICIONISTA guardado', [
                            'cups_id' => $cupsModel->id,
                            'codigo' => $cupsModel->codigo,
                            'nombre' => $cupsModel->nombre
                        ]);
                    }
                }
            }
        }

        DB::commit();

        // ✅ CARGAR RELACIONES (SIEMPRE INCLUYE COMPLEMENTARIA Y CUPS)
        $historia->load([
            'sede', 
            'cita.paciente', 
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups',
            'complementaria'
        ]);

        \Log::info('✅ Historia de nutricionista guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => true,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
            'cups_count' => $historia->historiaCups->count(),
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

private function storeInternista(Request $request, $cita)
{
    // ✅ VALIDACIÓN (SOLO CONTROL - NO HAY PRIMERA VEZ)
    $validationRules = [
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'tipo_consulta' => 'required|in:CONTROL', // ✅ SOLO CONTROL
        'motivo_consulta' => 'nullable|string',
        
        // ✅ DIAGNÓSTICOS (REQUERIDOS)
        'diagnosticos' => 'required|array|min:1',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        // ✅ MEDICAMENTOS
        'medicamentos' => 'nullable|array',
        'medicamentos.*.medicamento_id' => 'required_with:medicamentos|string',
        'medicamentos.*.cantidad' => 'nullable|string',
        'medicamentos.*.dosis' => 'nullable|string',
        
        // ✅ REMISIONES
        'remisiones' => 'nullable|array',
        'remisiones.*.remision_id' => 'required_with:remisiones|string',
        'remisiones.*.observacion' => 'nullable|string',
        
        // ✅ CUPS
        'cups' => 'nullable|array',
        'cups.*.cups_id' => 'required_with:cups|string',
        'cups.*.observacion' => 'nullable|string',
        
        // ✅ CAMPOS DE HISTORIA CLÍNICA BASE
        'finalidad' => 'nullable|string',
        'causa_externa' => 'nullable|string',
        'peso' => 'nullable|numeric',
        'talla' => 'nullable|numeric',
        'imc' => 'nullable|numeric',
        'clasificacion' => 'nullable|string',
        'acompanante' => 'nullable|string',
        'acu_telefono' => 'nullable|string',
        'acu_parentesco' => 'nullable|string',
        'enfermedad_actual' => 'nullable|string',
        'sistema_nervioso' => 'nullable|string',
        'ef_cabeza' => 'nullable|string',
        'obs_cabeza' => 'nullable|string',
        'cuello' => 'nullable|string',
        'obs_cuello' => 'nullable|string',
        'torax' => 'nullable|string',
        'obs_torax' => 'nullable|string',
        'abdomen' => 'nullable|string',
        'obs_abdomen' => 'nullable|string',
        'extremidades' => 'nullable|string',
        'obs_extremidades' => 'nullable|string',
        'perimetro_abdominal' => 'nullable|numeric',
        'presion_arterial_sistolica_sentado_pie' => 'nullable|numeric',
        'presion_arterial_distolica_sentado_pie' => 'nullable|numeric',
        'frecuencia_cardiaca' => 'nullable|numeric',
        'frecuencia_respiratoria' => 'nullable|numeric',
        'observaciones_generales' => 'nullable|string',
        'clasificacion_estado_metabolico' => 'nullable|string',
        'hipertension_arterial_personal' => 'nullable|string',
        'diabetes_mellitus_personal' => 'nullable|string',
        'clasificacion_hta' => 'nullable|string',
        'clasificacion_dm' => 'nullable|string',
        'clasificacion_erc_estado' => 'nullable|string',
        'clasificacion_erc_categoria_ambulatoria_persistente' => 'nullable|string',
        'clasificacion_rcv' => 'nullable|string',
        'tasa_filtracion_glomerular_ckd_epi' => 'nullable|numeric',
        'tasa_filtracion_glomerular_gockcroft_gault' => 'nullable|numeric',
        
        // ✅ CAMPOS DE TABLA COMPLEMENTARIA (CONTROL)
        'descripcion_sistema_nervioso' => 'nullable|string',
        'sistema_hemolinfatico' => 'nullable|string',
        'descripcion_sistema_hemolinfatico' => 'nullable|string',
        'aparato_digestivo' => 'nullable|string',
        'descripcion_aparato_digestivo' => 'nullable|string',
        'organo_sentido' => 'nullable|string',
        'descripcion_organos_sentidos' => 'nullable|string',
        'endocrino_metabolico' => 'nullable|string',
        'descripcion_endocrino_metabolico' => 'nullable|string',
        'inmunologico' => 'nullable|string',
        'descripcion_inmunologico' => 'nullable|string',
        'cancer_tumores_radioterapia_quimio' => 'nullable|string',
        'descripcion_cancer_tumores_radio_quimioterapia' => 'nullable|string',
        'glandula_mamaria' => 'nullable|string',
        'descripcion_glandulas_mamarias' => 'nullable|string',
        'hipertension_diabetes_erc' => 'nullable|string',
        'descripcion_hipertension_diabetes_erc' => 'nullable|string',
        'reacciones_alergica' => 'nullable|string',
        'descripcion_reacion_alergica' => 'nullable|string',
        'cardio_vasculares' => 'nullable|string',
        'descripcion_cardio_vasculares' => 'nullable|string',
        'respiratorios' => 'nullable|string',
        'descripcion_respiratorios' => 'nullable|string',
        'urinarias' => 'nullable|string',
        'descripcion_urinarias' => 'nullable|string',
        'osteoarticulares' => 'nullable|string',
        'descripcion_osteoarticulares' => 'nullable|string',
        'infecciosos' => 'nullable|string',
        'descripcion_infecciosos' => 'nullable|string',
        'cirugia_trauma' => 'nullable|string',
        'descripcion_cirugias_traumas' => 'nullable|string',
        'tratamiento_medicacion' => 'nullable|string',
        'descripcion_tratamiento_medicacion' => 'nullable|string',
        'antecedente_quirurgico' => 'nullable|string',
        'descripcion_antecedentes_quirurgicos' => 'nullable|string',
        'antecedentes_familiares' => 'nullable|string',
        'descripcion_antecedentes_familiares' => 'nullable|string',
        'consumo_tabaco' => 'nullable|string',
        'descripcion_consumo_tabaco' => 'nullable|string',
        'antecedentes_alcohol' => 'nullable|string',
        'descripcion_antecedentes_alcohol' => 'nullable|string',
        'sedentarismo' => 'nullable|string',
        'descripcion_sedentarismo' => 'nullable|string',
        'ginecologico' => 'nullable|string',
        'descripcion_ginecologicos' => 'nullable|string',
        'citologia_vaginal' => 'nullable|string',
        'descripcion_citologia_vaginal' => 'nullable|string',
        'menarquia' => 'nullable|string',
        'gestaciones' => 'nullable|integer',
        'parto' => 'nullable|integer',
        'aborto' => 'nullable|integer',
        'cesaria' => 'nullable|integer',
        'metodo_conceptivo' => 'nullable|string',
        'metodo_conceptivo_cual' => 'nullable|string',
        'antecedente_personal' => 'nullable|string',
        'neurologico_estado_mental' => 'nullable|string',
        'obs_neurologico_estado_mental' => 'nullable|string',
    ];

    $request->validate($validationRules);

    DB::beginTransaction();
    try {
        \Log::info('🩺 Guardando historia de INTERNISTA', [
            'cita_uuid' => $cita->uuid,
            'tipo_consulta' => $request->tipo_consulta,
            'paciente_uuid' => $request->paciente_uuid,
            'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
            'medicamentos_count' => $request->medicamentos ? count($request->medicamentos) : 0,
            'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
            'cups_count' => $request->cups ? count($request->cups) : 0,
        ]);

        // ✅ CREAR HISTORIA BASE (CONTROL)
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // Campos básicos
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'motivo_consulta' => $request->motivo_consulta ?? '',
            'enfermedad_actual' => $request->enfermedad_actual,
            
            // Acompañante
            'acompanante' => $request->acompanante,
            'acu_telefono' => $request->acu_telefono,
            'acu_parentesco' => $request->acu_parentesco,
            
            // Medidas antropométricas
            'peso' => $request->peso,
            'talla' => $request->talla,
            'imc' => $request->imc,
            'clasificacion' => $request->clasificacion,
            'perimetro_abdominal' => $request->perimetro_abdominal,
            
            // Signos vitales
            'presion_arterial_sistolica_sentado_pie' => $request->presion_arterial_sistolica_sentado_pie,
            'presion_arterial_distolica_sentado_pie' => $request->presion_arterial_distolica_sentado_pie,
            'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
            'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
            
            // Examen físico
            'sistema_nervioso' => $request->sistema_nervioso,
            'ef_cabeza' => $request->ef_cabeza,
            'obs_cabeza' => $request->obs_cabeza,
            'cuello' => $request->cuello,
            'obs_cuello' => $request->obs_cuello,
            'torax' => $request->torax,
            'obs_torax' => $request->obs_torax,
            'abdomen' => $request->abdomen,
            'obs_abdomen' => $request->obs_abdomen,
            'extremidades' => $request->extremidades,
            'obs_extremidades' => $request->obs_extremidades,
            
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
            'diabetes_mellitus_personal' => $request->diabetes_mellitus_personal ?? 'NO',
                // Observaciones
            'observaciones_generales' => $request->observaciones_generales,
        ]);

        \Log::info('✅ Historia clínica base creada', [
            'historia_id' => $historia->id,
            'historia_uuid' => $historia->uuid
        ]);

        // ✅ CREAR TABLA COMPLEMENTARIA (CONTROL - TODOS LOS ANTECEDENTES)
        \App\Models\HistoriaClinicaComplementaria::create([
            'uuid' => Str::uuid(),
            'historia_clinica_id' => $historia->id,
            
            // Sistema nervioso
            'descripcion_sistema_nervioso' => $request->descripcion_sistema_nervioso,
            
            // Sistema hemolinfático
            'sistema_hemolinfatico' => $request->sistema_hemolinfatico,
            'descripcion_sistema_hemolinfatico' => $request->descripcion_sistema_hemolinfatico,
            
            // Aparato digestivo
            'aparato_digestivo' => $request->aparato_digestivo,
            'descripcion_aparato_digestivo' => $request->descripcion_aparato_digestivo,
            
            // Órganos de los sentidos
            'organo_sentido' => $request->organo_sentido,
            'descripcion_organos_sentidos' => $request->descripcion_organos_sentidos,
            
            // Endocrino metabólico
            'endocrino_metabolico' => $request->endocrino_metabolico,
            'descripcion_endocrino_metabolico' => $request->descripcion_endocrino_metabolico,
            
            // Inmunológico
            'inmunologico' => $request->inmunologico,
            'descripcion_inmunologico' => $request->descripcion_inmunologico,
            
            // Cáncer/tumores
            'cancer_tumores_radioterapia_quimio' => $request->cancer_tumores_radioterapia_quimio,
            'descripcion_cancer_tumores_radio_quimioterapia' => $request->descripcion_cancer_tumores_radio_quimioterapia,
            
            // Glándula mamaria
            'glandula_mamaria' => $request->glandula_mamaria,
            'descripcion_glandulas_mamarias' => $request->descripcion_glandulas_mamarias,
            
            // HTA/DM/ERC
            'hipertension_diabetes_erc' => $request->hipertension_diabetes_erc,
            'descripcion_hipertension_diabetes_erc' => $request->descripcion_hipertension_diabetes_erc,
            
            // Reacciones alérgicas
            'reacciones_alergica' => $request->reacciones_alergica,
            'descripcion_reacion_alergica' => $request->descripcion_reacion_alergica,
            
            // Cardiovasculares
            'cardio_vasculares' => $request->cardio_vasculares,
            'descripcion_cardio_vasculares' => $request->descripcion_cardio_vasculares,
            
            // Respiratorios
            'respiratorios' => $request->respiratorios,
            'descripcion_respiratorios' => $request->descripcion_respiratorios,
            
            // Urinarias
            'urinarias' => $request->urinarias,
            'descripcion_urinarias' => $request->descripcion_urinarias,
            
            // Osteoarticulares
            'osteoarticulares' => $request->osteoarticulares,
            'descripcion_osteoarticulares' => $request->descripcion_osteoarticulares,
            
            // Infecciosos
            'infecciosos' => $request->infecciosos,
            'descripcion_infecciosos' => $request->descripcion_infecciosos,
            
            // Cirugía/trauma
            'cirugia_trauma' => $request->cirugia_trauma,
            'descripcion_cirugias_traumas' => $request->descripcion_cirugias_traumas,
            
            // Tratamiento/medicación
            'tratamiento_medicacion' => $request->tratamiento_medicacion,
            'descripcion_tratamiento_medicacion' => $request->descripcion_tratamiento_medicacion,
            
            // Antecedentes quirúrgicos
            'antecedente_quirurgico' => $request->antecedente_quirurgico,
            'descripcion_antecedentes_quirurgicos' => $request->descripcion_antecedentes_quirurgicos,
            
            // Antecedentes familiares
            'antecedentes_familiares' => $request->antecedentes_familiares,
            'descripcion_antecedentes_familiares' => $request->descripcion_antecedentes_familiares,
            
            // Hábitos
            'consumo_tabaco' => $request->consumo_tabaco,
            'descripcion_consumo_tabaco' => $request->descripcion_consumo_tabaco,
            'antecedentes_alcohol' => $request->antecedentes_alcohol,
            'descripcion_antecedentes_alcohol' => $request->descripcion_antecedentes_alcohol,
            'sedentarismo' => $request->sedentarismo,
            'descripcion_sedentarismo' => $request->descripcion_sedentarismo,
            
            // Ginecológicos
            'ginecologico' => $request->ginecologico,
            'descripcion_ginecologicos' => $request->descripcion_ginecologicos,
            'citologia_vaginal' => $request->citologia_vaginal,
            'descripcion_citologia_vaginal' => $request->descripcion_citologia_vaginal,
            'menarquia' => $request->menarquia,
            'gestaciones' => $request->gestaciones,
            'parto' => $request->parto,
            'aborto' => $request->aborto,
            'cesaria' => $request->cesaria,
            'metodo_conceptivo' => $request->metodo_conceptivo,
            'metodo_conceptivo_cual' => $request->metodo_conceptivo_cual,
            
            // Antecedentes personales
            'antecedente_personal' => $request->antecedente_personal,
            
            // Neurológico/estado mental
            'neurologico_estado_mental' => $request->neurologico_estado_mental,
            'obs_neurologico_estado_mental' => $request->obs_neurologico_estado_mental,
        ]);

        \Log::info('✅ Tabla complementaria creada (INTERNISTA - CONTROL)');

        // ✅ PROCESAR DIAGNÓSTICOS
        $diagnosticosProcesados = [];
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos INTERNISTA', [
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
                        \Log::info('✅ Diagnóstico INTERNISTA guardado', [
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR MEDICAMENTOS
        if ($request->has('medicamentos') && is_array($request->medicamentos)) {
            \Log::info('🔍 Procesando medicamentos INTERNISTA', [
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
                        \Log::info('✅ Medicamento INTERNISTA guardado', [
                            'medicamento_id' => $medicamento->id,
                            'nombre' => $medicamento->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones INTERNISTA', [
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
                        \Log::info('✅ Remisión INTERNISTA guardada', [
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR CUPS
        if ($request->has('cups') && is_array($request->cups)) {
            \Log::info('🔍 Procesando CUPS INTERNISTA', [
                'count' => count($request->cups)
            ]);
            
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
                        \Log::info('✅ CUPS INTERNISTA guardado', [
                            'cups_id' => $cupsModel->id,
                            'codigo' => $cupsModel->codigo,
                            'nombre' => $cupsModel->nombre
                        ]);
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
            'historiaCups.cups',
            'complementaria'
        ]);

        \Log::info('✅ Historia de internista guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => true,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
            'cups_count' => $historia->historiaCups->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Historia clínica de internista (CONTROL) guardada exitosamente",
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de internista', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de internista',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}

private function storeNefrologia(Request $request, $cita)
{
    // ✅ VALIDACIÓN (SOLO CONTROL - NO HAY PRIMERA VEZ)
    $validationRules = [
        'paciente_uuid' => 'required|string',
        'usuario_id' => 'required|integer',
        'sede_id' => 'required|integer',
        'tipo_consulta' => 'required|in:CONTROL', // ✅ SOLO CONTROL
        'motivo_consulta' => 'nullable|string',
        
        // ✅ DIAGNÓSTICOS (REQUERIDOS)
        'diagnosticos' => 'required|array|min:1',
        'diagnosticos.*.diagnostico_id' => 'required_with:diagnosticos|string',
        'diagnosticos.*.tipo' => 'required_with:diagnosticos|in:PRINCIPAL,SECUNDARIO',
        'diagnosticos.*.tipo_diagnostico' => 'required_with:diagnosticos|in:IMPRESION_DIAGNOSTICA,CONFIRMADO_NUEVO,CONFIRMADO_REPETIDO',
        
        // ✅ MEDICAMENTOS
        'medicamentos' => 'nullable|array',
        'medicamentos.*.medicamento_id' => 'required_with:medicamentos|string',
        'medicamentos.*.cantidad' => 'nullable|string',
        'medicamentos.*.dosis' => 'nullable|string',
        
        // ✅ REMISIONES
        'remisiones' => 'nullable|array',
        'remisiones.*.remision_id' => 'required_with:remisiones|string',
        'remisiones.*.observacion' => 'nullable|string',
        
        // ✅ CUPS
        'cups' => 'nullable|array',
        'cups.*.cups_id' => 'required_with:cups|string',
        'cups.*.observacion' => 'nullable|string',
        
        // ✅ CAMPOS DE HISTORIA CLÍNICA BASE
        'finalidad' => 'nullable|string',
        'causa_externa' => 'nullable|string',
        'peso' => 'nullable|numeric',
        'talla' => 'nullable|numeric',
        'imc' => 'nullable|numeric',
        'clasificacion' => 'nullable|string',
        'acompanante' => 'nullable|string',
        'acu_telefono' => 'nullable|string',
        'acu_parentesco' => 'nullable|string',
        'enfermedad_actual' => 'nullable|string',
        'sistema_nervioso' => 'nullable|string',
        'ef_cabeza' => 'nullable|string',
        'obs_cabeza' => 'nullable|string',
        'cuello' => 'nullable|string',
        'obs_cuello' => 'nullable|string',
        'torax' => 'nullable|string',
        'obs_torax' => 'nullable|string',
        'abdomen' => 'nullable|string',
        'obs_abdomen' => 'nullable|string',
        'extremidades' => 'nullable|string',
        'obs_extremidades' => 'nullable|string',
        'perimetro_abdominal' => 'nullable|numeric',
        'presion_arterial_sistolica_sentado_pie' => 'nullable|numeric',
        'presion_arterial_distolica_sentado_pie' => 'nullable|numeric',
        'frecuencia_cardiaca' => 'nullable|numeric',
        'frecuencia_respiratoria' => 'nullable|numeric',
        'observaciones_generales' => 'nullable|string',
        'clasificacion_estado_metabolico' => 'nullable|string',
        'hipertension_arterial_personal' => 'nullable|string',
        'diabetes_mellitus_personal' => 'nullable|string',
        'clasificacion_hta' => 'nullable|string',
        'clasificacion_dm' => 'nullable|string',
        'clasificacion_erc_estado' => 'nullable|string',
        'clasificacion_erc_categoria_ambulatoria_persistente' => 'nullable|string',
        'clasificacion_rcv' => 'nullable|string',
        'tasa_filtracion_glomerular_ckd_epi' => 'nullable|numeric',
        'tasa_filtracion_glomerular_gockcroft_gault' => 'nullable|numeric',

        // ✅ CAMPOS DE TABLA COMPLEMENTARIA (CONTROL)
        'descripcion_sistema_nervioso' => 'nullable|string',
        'sistema_hemolinfatico' => 'nullable|string',
        'descripcion_sistema_hemolinfatico' => 'nullable|string',
        'aparato_digestivo' => 'nullable|string',
        'descripcion_aparato_digestivo' => 'nullable|string',
        'organo_sentido' => 'nullable|string',
        'descripcion_organos_sentidos' => 'nullable|string',
        'endocrino_metabolico' => 'nullable|string',
        'descripcion_endocrino_metabolico' => 'nullable|string',
        'inmunologico' => 'nullable|string',
        'descripcion_inmunologico' => 'nullable|string',
        'cancer_tumores_radioterapia_quimio' => 'nullable|string',
        'descripcion_cancer_tumores_radio_quimioterapia' => 'nullable|string',
        'glandula_mamaria' => 'nullable|string',
        'descripcion_glandulas_mamarias' => 'nullable|string',
        'hipertension_diabetes_erc' => 'nullable|string',
        'descripcion_hipertension_diabetes_erc' => 'nullable|string',
        'reacciones_alergica' => 'nullable|string',
        'descripcion_reacion_alergica' => 'nullable|string',
        'cardio_vasculares' => 'nullable|string',
        'descripcion_cardio_vasculares' => 'nullable|string',
        'respiratorios' => 'nullable|string',
        'descripcion_respiratorios' => 'nullable|string',
        'urinarias' => 'nullable|string',
        'descripcion_urinarias' => 'nullable|string',
        'osteoarticulares' => 'nullable|string',
        'descripcion_osteoarticulares' => 'nullable|string',
        'infecciosos' => 'nullable|string',
        'descripcion_infecciosos' => 'nullable|string',
        'cirugia_trauma' => 'nullable|string',
        'descripcion_cirugias_traumas' => 'nullable|string',
        'tratamiento_medicacion' => 'nullable|string',
        'descripcion_tratamiento_medicacion' => 'nullable|string',
        'antecedente_quirurgico' => 'nullable|string',
        'descripcion_antecedentes_quirurgicos' => 'nullable|string',
        'antecedentes_familiares' => 'nullable|string',
        'descripcion_antecedentes_familiares' => 'nullable|string',
        'consumo_tabaco' => 'nullable|string',
        'descripcion_consumo_tabaco' => 'nullable|string',
        'antecedentes_alcohol' => 'nullable|string',
        'descripcion_antecedentes_alcohol' => 'nullable|string',
        'sedentarismo' => 'nullable|string',
        'descripcion_sedentarismo' => 'nullable|string',
        'ginecologico' => 'nullable|string',
        'descripcion_ginecologicos' => 'nullable|string',
        'citologia_vaginal' => 'nullable|string',
        'descripcion_citologia_vaginal' => 'nullable|string',
        'menarquia' => 'nullable|string',
        'gestaciones' => 'nullable|integer',
        'parto' => 'nullable|integer',
        'aborto' => 'nullable|integer',
        'cesaria' => 'nullable|integer',
        'metodo_conceptivo' => 'nullable|string',
        'metodo_conceptivo_cual' => 'nullable|string',
        'antecedente_personal' => 'nullable|string',
        'neurologico_estado_mental' => 'nullable|string',
        'obs_neurologico_estado_mental' => 'nullable|string',
    ];

    $request->validate($validationRules);

    DB::beginTransaction();
    try {
        \Log::info('🩺 Guardando historia de INTERNISTA', [
            'cita_uuid' => $cita->uuid,
            'tipo_consulta' => $request->tipo_consulta,
            'paciente_uuid' => $request->paciente_uuid,
            'diagnosticos_count' => $request->diagnosticos ? count($request->diagnosticos) : 0,
            'medicamentos_count' => $request->medicamentos ? count($request->medicamentos) : 0,
            'remisiones_count' => $request->remisiones ? count($request->remisiones) : 0,
            'cups_count' => $request->cups ? count($request->cups) : 0,
        ]);

        // ✅ CREAR HISTORIA BASE (CONTROL)
        $historia = HistoriaClinica::create([
            'uuid' => $request->uuid ?? Str::uuid(),
            'sede_id' => $request->sede_id,
            'cita_id' => $cita->id,
            
            // Campos básicos
            'finalidad' => $request->finalidad ?? 'CONSULTA',
            'causa_externa' => $request->causa_externa,
            'motivo_consulta' => $request->motivo_consulta ?? '',
            'enfermedad_actual' => $request->enfermedad_actual,
            
            // Acompañante
            'acompanante' => $request->acompanante,
            'acu_telefono' => $request->acu_telefono,
            'acu_parentesco' => $request->acu_parentesco,
            
            // Medidas antropométricas
            'peso' => $request->peso,
            'talla' => $request->talla,
            'imc' => $request->imc,
            'clasificacion' => $request->clasificacion,
            'perimetro_abdominal' => $request->perimetro_abdominal,
            
            // Signos vitales
            'presion_arterial_sistolica_sentado_pie' => $request->presion_arterial_sistolica_sentado_pie,
            'presion_arterial_distolica_sentado_pie' => $request->presion_arterial_distolica_sentado_pie,
            'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
            'frecuencia_respiratoria' => $request->frecuencia_respiratoria,
            
            // Examen físico
            'sistema_nervioso' => $request->sistema_nervioso,
            'ef_cabeza' => $request->ef_cabeza,
            'obs_cabeza' => $request->obs_cabeza,
            'cuello' => $request->cuello,
            'obs_cuello' => $request->obs_cuello,
            'torax' => $request->torax,
            'obs_torax' => $request->obs_torax,
            'abdomen' => $request->abdomen,
            'obs_abdomen' => $request->obs_abdomen,
            'extremidades' => $request->extremidades,
            'obs_extremidades' => $request->obs_extremidades,
            
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
            'diabetes_mellitus_personal' => $request->diabetes_mellitus_personal ?? 'NO',
                // Observaciones
            'observaciones_generales' => $request->observaciones_generales,
        ]);

        \Log::info('✅ Historia clínica base creada', [
            'historia_id' => $historia->id,
            'historia_uuid' => $historia->uuid
        ]);

        // ✅ CREAR TABLA COMPLEMENTARIA (CONTROL - TODOS LOS ANTECEDENTES)
        \App\Models\HistoriaClinicaComplementaria::create([
            'uuid' => Str::uuid(),
            'historia_clinica_id' => $historia->id,
            
            // Sistema nervioso
            'descripcion_sistema_nervioso' => $request->descripcion_sistema_nervioso,
            
            // Sistema hemolinfático
            'sistema_hemolinfatico' => $request->sistema_hemolinfatico,
            'descripcion_sistema_hemolinfatico' => $request->descripcion_sistema_hemolinfatico,
            
            // Aparato digestivo
            'aparato_digestivo' => $request->aparato_digestivo,
            'descripcion_aparato_digestivo' => $request->descripcion_aparato_digestivo,
            
            // Órganos de los sentidos
            'organo_sentido' => $request->organo_sentido,
            'descripcion_organos_sentidos' => $request->descripcion_organos_sentidos,
            
            // Endocrino metabólico
            'endocrino_metabolico' => $request->endocrino_metabolico,
            'descripcion_endocrino_metabolico' => $request->descripcion_endocrino_metabolico,
            
            // Inmunológico
            'inmunologico' => $request->inmunologico,
            'descripcion_inmunologico' => $request->descripcion_inmunologico,
            
            // Cáncer/tumores
            'cancer_tumores_radioterapia_quimio' => $request->cancer_tumores_radioterapia_quimio,
            'descripcion_cancer_tumores_radio_quimioterapia' => $request->descripcion_cancer_tumores_radio_quimioterapia,
            
            // Glándula mamaria
            'glandula_mamaria' => $request->glandula_mamaria,
            'descripcion_glandulas_mamarias' => $request->descripcion_glandulas_mamarias,
            
            // HTA/DM/ERC
            'hipertension_diabetes_erc' => $request->hipertension_diabetes_erc,
            'descripcion_hipertension_diabetes_erc' => $request->descripcion_hipertension_diabetes_erc,
            
            // Reacciones alérgicas
            'reacciones_alergica' => $request->reacciones_alergica,
            'descripcion_reacion_alergica' => $request->descripcion_reacion_alergica,
            
            // Cardiovasculares
            'cardio_vasculares' => $request->cardio_vasculares,
            'descripcion_cardio_vasculares' => $request->descripcion_cardio_vasculares,
            
            // Respiratorios
            'respiratorios' => $request->respiratorios,
            'descripcion_respiratorios' => $request->descripcion_respiratorios,
            
            // Urinarias
            'urinarias' => $request->urinarias,
            'descripcion_urinarias' => $request->descripcion_urinarias,
            
            // Osteoarticulares
            'osteoarticulares' => $request->osteoarticulares,
            'descripcion_osteoarticulares' => $request->descripcion_osteoarticulares,
            
            // Infecciosos
            'infecciosos' => $request->infecciosos,
            'descripcion_infecciosos' => $request->descripcion_infecciosos,
            
            // Cirugía/trauma
            'cirugia_trauma' => $request->cirugia_trauma,
            'descripcion_cirugias_traumas' => $request->descripcion_cirugias_traumas,
            
            // Tratamiento/medicación
            'tratamiento_medicacion' => $request->tratamiento_medicacion,
            'descripcion_tratamiento_medicacion' => $request->descripcion_tratamiento_medicacion,
            
            // Antecedentes quirúrgicos
            'antecedente_quirurgico' => $request->antecedente_quirurgico,
            'descripcion_antecedentes_quirurgicos' => $request->descripcion_antecedentes_quirurgicos,
            
            // Antecedentes familiares
            'antecedentes_familiares' => $request->antecedentes_familiares,
            'descripcion_antecedentes_familiares' => $request->descripcion_antecedentes_familiares,
            
            // Hábitos
            'consumo_tabaco' => $request->consumo_tabaco,
            'descripcion_consumo_tabaco' => $request->descripcion_consumo_tabaco,
            'antecedentes_alcohol' => $request->antecedentes_alcohol,
            'descripcion_antecedentes_alcohol' => $request->descripcion_antecedentes_alcohol,
            'sedentarismo' => $request->sedentarismo,
            'descripcion_sedentarismo' => $request->descripcion_sedentarismo,
            
            // Ginecológicos
            'ginecologico' => $request->ginecologico,
            'descripcion_ginecologicos' => $request->descripcion_ginecologicos,
            'citologia_vaginal' => $request->citologia_vaginal,
            'descripcion_citologia_vaginal' => $request->descripcion_citologia_vaginal,
            'menarquia' => $request->menarquia,
            'gestaciones' => $request->gestaciones,
            'parto' => $request->parto,
            'aborto' => $request->aborto,
            'cesaria' => $request->cesaria,
            'metodo_conceptivo' => $request->metodo_conceptivo,
            'metodo_conceptivo_cual' => $request->metodo_conceptivo_cual,
            
            // Antecedentes personales
            'antecedente_personal' => $request->antecedente_personal,
            
            // Neurológico/estado mental
            'neurologico_estado_mental' => $request->neurologico_estado_mental,
            'obs_neurologico_estado_mental' => $request->obs_neurologico_estado_mental,
        ]);

        \Log::info('✅ Tabla complementaria creada (INTERNISTA - CONTROL)');

        // ✅ PROCESAR DIAGNÓSTICOS
        $diagnosticosProcesados = [];
        
        if ($request->has('diagnosticos') && is_array($request->diagnosticos)) {
            \Log::info('🔍 Procesando array diagnosticos INTERNISTA', [
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
                        \Log::info('✅ Diagnóstico INTERNISTA guardado', [
                            'diagnostico_id' => $diagnostico->id,
                            'codigo' => $diagnostico->codigo
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR MEDICAMENTOS
        if ($request->has('medicamentos') && is_array($request->medicamentos)) {
            \Log::info('🔍 Procesando medicamentos INTERNISTA', [
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
                        \Log::info('✅ Medicamento INTERNISTA guardado', [
                            'medicamento_id' => $medicamento->id,
                            'nombre' => $medicamento->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR REMISIONES
        if ($request->has('remisiones') && is_array($request->remisiones)) {
            \Log::info('🔍 Procesando remisiones INTERNISTA', [
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
                        \Log::info('✅ Remisión INTERNISTA guardada', [
                            'remision_id' => $remision->id,
                            'nombre' => $remision->nombre
                        ]);
                    }
                }
            }
        }

        // ✅ PROCESAR CUPS
        if ($request->has('cups') && is_array($request->cups)) {
            \Log::info('🔍 Procesando CUPS INTERNISTA', [
                'count' => count($request->cups)
            ]);
            
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
                        \Log::info('✅ CUPS INTERNISTA guardado', [
                            'cups_id' => $cupsModel->id,
                            'codigo' => $cupsModel->codigo,
                            'nombre' => $cupsModel->nombre
                        ]);
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
            'historiaCups.cups',
            'complementaria'
        ]);

        \Log::info('✅ Historia de internista guardada exitosamente', [
            'tipo_consulta' => $request->tipo_consulta,
            'historia_uuid' => $historia->uuid,
            'tiene_complementaria' => true,
            'diagnosticos_count' => $historia->historiaDiagnosticos->count(),
            'medicamentos_count' => $historia->historiaMedicamentos->count(),
            'remisiones_count' => $historia->historiaRemisiones->count(),
            'cups_count' => $historia->historiaCups->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Historia clínica de internista (CONTROL) guardada exitosamente",
            'data' => $historia
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('❌ Error guardando historia de internista', [
            'error' => $e->getMessage(),
            'tipo_consulta' => $request->tipo_consulta,
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al crear historia clínica de internista',
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
 * ✅ MOSTRAR HISTORIA CLÍNICA COMPLETA PARA VISTA/IMPRESIÓN
 * Recupera TODOS los campos de la historia clínica con sus relaciones
 */
    public function show($uuid)
    {
        try {
            Log::info('📋 Obteniendo historia clínica completa', [
                'historia_uuid' => $uuid
            ]);

            // ✅ CARGAR HISTORIA CON TODAS LAS RELACIONES
            $historia = HistoriaClinica::with([
                // ✅ SEDE
                'sede',
                
                // ✅ CITA (CON FECHA Y HORA)
                'cita',
                'cita.paciente', // Datos del paciente
                'cita.agenda', // Agenda de la cita
                'cita.paciente.empresa',
                'cita.paciente.regimen',   // ← Relación del paciente
                'cita.paciente.ocupacion',  
                'cita.paciente.brigada',
                'cita.agenda.usuario', // Usuario que creó la agenda
                'cita.agenda.usuarioMedico', // Médico asignado
                'cita.agenda.usuarioMedico.especialidad', // Especialidad del médico
                'cita.agenda.proceso', // Proceso/Especialidad de la cita
                'cita.cupsContratado', // CUPS contratado
                'cita.cupsContratado.categoriaCups', // Categoría CUPS (PRIMERA VEZ/CONTROL)
                
                // ✅ DIAGNÓSTICOS
                'historiaDiagnosticos.diagnostico',
                
                // ✅ MEDICAMENTOS
                'historiaMedicamentos.medicamento',
                
                // ✅ REMISIONES
                'historiaRemisiones.remision',
                
                // ✅ CUPS
                'historiaCups.cups',
                
                // ✅ COMPLEMENTARIA (FISIOTERAPIA, PSICOLOGÍA, NUTRICIÓN, ETC.)
                'complementaria'
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

            Log::info('✅ Historia clínica encontrada', [
                'historia_uuid' => $historia->uuid,
                'historia_id' => $historia->id,
                'paciente' => $historia->cita->paciente->nombre_completo ?? 'N/A',
                'fecha_cita' => $historia->cita->fecha ?? 'N/A',
                'especialidad' => $historia->cita->agenda->proceso->nombre ?? 'N/A'
            ]);

            // ✅ OBTENER TIPO DE CONSULTA DESDE CUPS CONTRATADO
            $tipoConsulta = $this->obtenerTipoConsulta($historia);

            // ✅ OBTENER ESPECIALIDAD
            $especialidad = $historia->cita->agenda->proceso->nombre ?? 
                        $historia->cita->agenda->usuarioMedico->especialidad->nombre ?? 
                        'MEDICINA GENERAL';

            // ✅ FORMATEAR DATOS PARA EL FRONTEND
            $historiaCompleta = [
                // ═══════════════════════════════════════════
                // 🔹 INFORMACIÓN BÁSICA DE LA HISTORIA
                // ═══════════════════════════════════════════
                'uuid' => $historia->uuid,
                'id' => $historia->id,
                'sede_id' => $historia->sede_id,
                'cita_id' => $historia->cita_id,
                'created_at' => $historia->created_at ? $historia->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $historia->updated_at ? $historia->updated_at->format('Y-m-d H:i:s') : null,
                
                // ═══════════════════════════════════════════
                // 🔹 INFORMACIÓN DE LA CITA (CON FECHA)
                // ═══════════════════════════════════════════
                'cita' => [
                    'uuid' => $historia->cita->uuid ?? null,
                    'fecha' => $historia->cita->fecha ?? null, // ✅ FECHA DE LA CITA
                    'hora' => $historia->cita->hora ?? null,
                    'estado' => $historia->cita->estado ?? null,
                    
                    // ✅ PACIENTE
                    'paciente' => $historia->cita && $historia->cita->paciente ? [
                        'uuid' => $historia->cita->paciente->uuid,
                        'nombre_completo' => $historia->cita->paciente->nombre_completo ?? 
                                            trim(($historia->cita->paciente->primer_nombre ?? '') . ' ' . 
                                                ($historia->cita->paciente->segundo_nombre ?? '') . ' ' . 
                                                ($historia->cita->paciente->primer_apellido ?? '') . ' ' . 
                                                ($historia->cita->paciente->segundo_apellido ?? '')),
                        'tipo_documento' => $historia->cita->paciente->tipo_documento ?? 'CC',
                        'documento' => $historia->cita->paciente->documento ?? 'N/A',
                        'estado_civil' => $historia->cita->paciente->estado_civil ?? null,
                        'fecha_nacimiento' => $historia->cita->paciente->fecha_nacimiento ?? null,
                        'sexo' => $historia->cita->paciente->sexo ?? null,
                        'telefono' => $historia->cita->paciente->telefono ?? null,
                        'direccion' => $historia->cita->paciente->direccion ?? null,
                        'email' => $historia->cita->paciente->email ?? null,
                            // ✅ RÉGIMEN (← ESTO ES LO QUE FALTABA)
                        'regimen' => $historia->cita->paciente->regimen ? [
                        'uuid' => $historia->cita->paciente->regimen->uuid ?? $historia->cita->paciente->regimen->id,
                        'nombre' => $historia->cita->paciente->regimen->nombre ?? 'N/A',
                        'codigo' => $historia->cita->paciente->regimen->codigo ?? null,
                        ] : null,
                    // ✅ EMPRESA (IGUAL QUE RÉGIMEN)
                    'empresa' => $historia->cita->paciente->empresa ? [
                        'uuid' => $historia->cita->paciente->empresa->uuid ?? $historia->cita->paciente->empresa->id,
                        'nombre' => $historia->cita->paciente->empresa->nombre ?? 'N/A',
                        'nit' => $historia->cita->paciente->empresa->nit ?? null,
                    ] : null,

                    // ✅ OCUPACIÓN (IGUAL QUE RÉGIMEN)
                    'ocupacion' => $historia->cita->paciente->ocupacion ? [
                        'uuid' => $historia->cita->paciente->ocupacion->uuid ?? $historia->cita->paciente->ocupacion->id,
                        'nombre' => $historia->cita->paciente->ocupacion->nombre ?? 'N/A',
                        'codigo' => $historia->cita->paciente->ocupacion->codigo ?? null,
                    ] : null,

                    'brigada' => $historia->cita->paciente->brigada ? [
                    'uuid' => $historia->cita->paciente->brigada->uuid ?? $historia->cita->paciente->brigada->id,
                    'nombre' => $historia->cita->paciente->brigada->nombre ?? 'N/A',
                    ] : null,
                    
                    ] : null,
                    
                    // ✅ AGENDA CON PROFESIONAL Y ESPECIALIDAD
                   'agenda' => $historia->cita && $historia->cita->agenda ? [
                    'uuid' => $historia->cita->agenda->uuid,
                    
                    // ✅ PROCESO (ESPECIALIDAD)
                    'proceso' => $historia->cita->agenda->proceso ? [
                        'uuid' => $historia->cita->agenda->proceso->uuid,
                        'nombre' => $historia->cita->agenda->proceso->nombre ?? 'N/A',
                    ] : null,
                    
                    // ✅ PROFESIONAL (MÉDICO ASIGNADO)
                    'usuario_medico' => $historia->cita->agenda->usuarioMedico ? [
                        'uuid' => $historia->cita->agenda->usuarioMedico->uuid,
                        
                        // ✅ NOMBRE COMPLETO
                        'nombre_completo' => $historia->cita->agenda->usuarioMedico->nombre_completo ?? 
                                            trim(($historia->cita->agenda->usuarioMedico->nombre ?? '') . ' ' . 
                                                ($historia->cita->agenda->usuarioMedico->apellido ?? '')),
                        
                        // ✅ DOCUMENTO
                        'documento' => $historia->cita->agenda->usuarioMedico->documento ?? null,
                        
                        // ✅ REGISTRO PROFESIONAL (← CORREGIDO)
                        'registro_profesional' => $historia->cita->agenda->usuarioMedico->registro_profesional ?? null,
                        
                        // ✅ FIRMA EN BASE64 (← NUEVO CAMPO)
                        'firma' => $historia->cita->agenda->usuarioMedico->firma ?? null,
                        
                        // ✅ ESPECIALIDAD
                        'especialidad' => $historia->cita->agenda->usuarioMedico->especialidad ? [
                            'uuid' => $historia->cita->agenda->usuarioMedico->especialidad->uuid,
                            'nombre' => $historia->cita->agenda->usuarioMedico->especialidad->nombre,
                        ] : null,
                        
                    ] : (
                        // ✅ FALLBACK: SI NO HAY MÉDICO ASIGNADO, USAR USUARIO QUE CREÓ LA AGENDA
                        $historia->cita->agenda->usuario ? [
                            'uuid' => $historia->cita->agenda->usuario->uuid,
                            'nombre_completo' => $historia->cita->agenda->usuario->nombre_completo ?? 
                                                trim(($historia->cita->agenda->usuario->nombre ?? '') . ' ' . 
                                                    ($historia->cita->agenda->usuario->apellido ?? '')),
                            'documento' => $historia->cita->agenda->usuario->documento ?? null,
                            'registro_profesional' => $historia->cita->agenda->usuario->registro_profesional ?? null,
                            'firma' => $historia->cita->agenda->usuario->firma ?? null, // ✅ FIRMA TAMBIÉN AQUÍ
                        ] : null
                    ),
                ] : null,

                ],
                
                // ═══════════════════════════════════════════
                // 🔹 SEDE
                // ═══════════════════════════════════════════
                'sede' => $historia->sede ? [
                    'uuid' => $historia->sede->uuid,
                    'nombre' => $historia->sede->nombre ?? 'N/A',
                    'direccion' => $historia->sede->direccion ?? null,
                    'telefono' => $historia->sede->telefono ?? null,
                ] : null,
                
                // ═══════════════════════════════════════════
                // 🔹 TIPO DE CONSULTA Y ESPECIALIDAD
                // ═══════════════════════════════════════════
                'tipo_consulta' => $tipoConsulta,
                'especialidad' => $especialidad,
                
                // ═══════════════════════════════════════════
                // 🔹 MOTIVO Y ENFERMEDAD ACTUAL
                // ═══════════════════════════════════════════
                'motivo_consulta' => $historia->motivo_consulta,
                'enfermedad_actual' => $historia->enfermedad_actual,
                
                // ═══════════════════════════════════════════
                // 🔹 FINALIDAD Y CAUSA EXTERNA
                // ═══════════════════════════════════════════
                'finalidad' => $historia->finalidad,
                'causa_externa' => $historia->causa_externa,
                
                // ═══════════════════════════════════════════
                // 🔹 ACOMPAÑANTE
                // ═══════════════════════════════════════════
                'acompanante' => $historia->acompanante,
                'acu_parentesco' => $historia->acu_parentesco,
                'acu_telefono' => $historia->acu_telefono,
                
                // ═══════════════════════════════════════════
                // 🔹 MEDIDAS ANTROPOMÉTRICAS
                // ═══════════════════════════════════════════
                'peso' => $historia->peso,
                'talla' => $historia->talla,
                'imc' => $historia->imc,
                'clasificacion' => $historia->clasificacion,
                'perimetro_abdominal' => $historia->perimetro_abdominal,
                'obs_perimetro_abdominal' => $historia->obs_perimetro_abdominal,
                
                // ═══════════════════════════════════════════
                // 🔹 SIGNOS VITALES
                // ═══════════════════════════════════════════
                'presion_arterial_sistolica_sentado_pie' => $historia->presion_arterial_sistolica_sentado_pie,
                'presion_arterial_distolica_sentado_pie' => $historia->presion_arterial_distolica_sentado_pie,
                'presion_arterial_sistolica_acostado' => $historia->presion_arterial_sistolica_acostado,
                'presion_arterial_distolica_acostado' => $historia->presion_arterial_distolica_acostado,
                'frecuencia_cardiaca' => $historia->frecuencia_cardiaca,
                'frecuencia_respiratoria' => $historia->frecuencia_respiratoria,
                
                // ═══════════════════════════════════════════
                // 🔹 TEST DE MORISKY
                // ═══════════════════════════════════════════
                'olvida_tomar_medicamentos' => $historia->olvida_tomar_medicamentos,
                'toma_medicamentos_hora_indicada' => $historia->toma_medicamentos_hora_indicada,
                'cuando_esta_bien_deja_tomar_medicamentos' => $historia->cuando_esta_bien_deja_tomar_medicamentos,
                'siente_mal_deja_tomarlos' => $historia->siente_mal_deja_tomarlos,
                'valoracion_psicologia' => $historia->valoracion_psicologia,
                'adherente' => $historia->adherente,
                
                // ═══════════════════════════════════════════
                // 🔹 REVISIÓN POR SISTEMAS
                // ═══════════════════════════════════════════
                'general' => $historia->general,
                'cabeza' => $historia->cabeza,
                'orl' => $historia->orl,
                'respiratorio' => $historia->respiratorio,
                'cardiovascular' => $historia->cardiovascular,
                'gastrointestinal' => $historia->gastrointestinal,
                'osteoatromuscular' => $historia->osteoatromuscular,
                'snc' => $historia->snc,
                'revision_sistemas' => $historia->revision_sistemas,
                
                // ═══════════════════════════════════════════
                // 🔹 EXAMEN FÍSICO
                // ═══════════════════════════════════════════
                'ef_cabeza' => $historia->ef_cabeza,
                'obs_cabeza' => $historia->obs_cabeza,
                'agudeza_visual' => $historia->agudeza_visual,
                'obs_agudeza_visual' => $historia->obs_agudeza_visual,
                'fundoscopia' => $historia->fundoscopia,
                'obs_fundoscopia' => $historia->obs_fundoscopia,
                'oidos' => $historia->oidos,
                'nariz_senos_paranasales' => $historia->nariz_senos_paranasales,
                'cavidad_oral' => $historia->cavidad_oral,
                'cuello' => $historia->cuello,
                'obs_cuello' => $historia->obs_cuello,
                'cardio_respiratorio' => $historia->cardio_respiratorio,
                'torax' => $historia->torax,
                'obs_torax' => $historia->obs_torax,
                'mamas' => $historia->mamas,
                'obs_mamas' => $historia->obs_mamas,
                'abdomen' => $historia->abdomen,
                'obs_abdomen' => $historia->obs_abdomen,
                'genito_urinario' => $historia->genito_urinario,
                'obs_genito_urinario' => $historia->obs_genito_urinario,
                'musculo_esqueletico' => $historia->musculo_esqueletico,
                'extremidades' => $historia->extremidades,
                'obs_extremidades' => $historia->obs_extremidades,
                'piel_anexos_pulsos' => $historia->piel_anexos_pulsos,
                'obs_piel_anexos_pulsos' => $historia->obs_piel_anexos_pulsos,
                'inspeccion_sensibilidad_pies' => $historia->inspeccion_sensibilidad_pies,
                'sistema_nervioso' => $historia->sistema_nervioso,
                'obs_sistema_nervioso' => $historia->obs_sistema_nervioso,
                'capacidad_cognitiva' => $historia->capacidad_cognitiva,
                'obs_capacidad_cognitiva' => $historia->obs_capacidad_cognitiva,
                'capacidad_cognitiva_orientacion' => $historia->capacidad_cognitiva_orientacion,
                'orientacion' => $historia->orientacion,
                'obs_orientacion' => $historia->obs_orientacion,
                'reflejo_aquiliar' => $historia->reflejo_aquiliar,
                'obs_reflejo_aquiliar' => $historia->obs_reflejo_aquiliar,
                'reflejo_patelar' => $historia->reflejo_patelar,
                'obs_reflejo_patelar' => $historia->obs_reflejo_patelar,
                'hallazgo_positivo_examen_fisico' => $historia->hallazgo_positivo_examen_fisico,
                
                // ═══════════════════════════════════════════
                // 🔹 FACTORES DE RIESGO
                // ═══════════════════════════════════════════
                'tabaquismo' => $historia->tabaquismo,
                'obs_tabaquismo' => $historia->obs_tabaquismo,
                'dislipidemia' => $historia->dislipidemia,
                'obs_dislipidemia' => $historia->obs_dislipidemia,
                'menor_cierta_edad' => $historia->menor_cierta_edad,
                'obs_menor_cierta_edad' => $historia->obs_menor_cierta_edad,
                'condicion_clinica_asociada' => $historia->condicion_clinica_asociada,
                'obs_condicion_clinica_asociada' => $historia->obs_condicion_clinica_asociada,
                'lesion_organo_blanco' => $historia->lesion_organo_blanco,
                'obs_lesion_organo_blanco' => $historia->obs_lesion_organo_blanco,
                'descripcion_lesion_organo_blanco' => $historia->descripcion_lesion_organo_blanco,
                
                // ═══════════════════════════════════════════
                // 🔹 EXÁMENES COMPLEMENTARIOS
                // ═══════════════════════════════════════════
                'fex_es' => $historia->fex_es,
                'electrocardiograma' => $historia->electrocardiograma,
                'fex_es1' => $historia->fex_es1,
                'ecocardiograma' => $historia->ecocardiograma,
                'fex_es2' => $historia->fex_es2,
                'ecografia_renal' => $historia->ecografia_renal,
                
                // ═══════════════════════════════════════════
                // 🔹 CLASIFICACIONES
                // ═══════════════════════════════════════════
                'clasificacion_estado_metabolico' => $historia->clasificacion_estado_metabolico,
                'clasificacion_hta' => $historia->clasificacion_hta,
                'clasificacion_dm' => $historia->clasificacion_dm,
                'clasificacion_rcv' => $historia->clasificacion_rcv,
                'clasificacion_erc_estado' => $historia->clasificacion_erc_estado,
                'clasificacion_erc_categoria_ambulatoria_persistente' => $historia->clasificacion_erc_categoria_ambulatoria_persistente,
                
                // ═══════════════════════════════════════════
                // 🔹 TASAS DE FILTRACIÓN
                // ═══════════════════════════════════════════
                'tasa_filtracion_glomerular_ckd_epi' => $historia->tasa_filtracion_glomerular_ckd_epi,
                'tasa_filtracion_glomerular_gockcroft_gault' => $historia->tasa_filtracion_glomerular_gockcroft_gault,
                
                // ═══════════════════════════════════════════
                // 🔹 ANTECEDENTES PERSONALES
                // ═══════════════════════════════════════════
                'hipertension_arterial_personal' => $historia->hipertension_arterial_personal,
                'obs_personal_hipertension_arterial' => $historia->obs_personal_hipertension_arterial,
                'diabetes_mellitus_personal' => $historia->diabetes_mellitus_personal,
                'obs_personal_mellitus' => $historia->obs_personal_mellitus,
                'enfermedad_cardiovascular_personal' => $historia->enfermedad_cardiovascular_personal,
                'obs_personal_enfermedad_cardiovascular' => $historia->obs_personal_enfermedad_cardiovascular,
                'arterial_periferica_personal' => $historia->arterial_periferica_personal,
                'obs_personal_arterial_periferica' => $historia->obs_personal_arterial_periferica,
                'carotidea_personal' => $historia->carotidea_personal,
                'obs_personal_carotidea' => $historia->obs_personal_carotidea,
                'aneurisma_aorta_personal' => $historia->aneurisma_aorta_personal,
                'obs_personal_aneurisma_aorta' => $historia->obs_personal_aneurisma_aorta,
                'sindrome_coronario_agudo_angina_personal' => $historia->sindrome_coronario_agudo_angina_personal,
                'obs_personal_sindrome_coronario' => $historia->obs_personal_sindrome_coronario,
                'artritis_personal' => $historia->artritis_personal,
                'obs_personal_artritis' => $historia->obs_personal_artritis,
                'iam_personal' => $historia->iam_personal,
                'obs_personal_iam' => $historia->obs_personal_iam,
                'revascul_coronaria_personal' => $historia->revascul_coronaria_personal,
                'obs_personal_revascul_coronaria' => $historia->obs_personal_revascul_coronaria,
                'insuficiencia_cardiaca_personal' => $historia->insuficiencia_cardiaca_personal,
                'obs_personal_insuficiencia_cardiaca' => $historia->obs_personal_insuficiencia_cardiaca,
                'amputacion_pie_diabetico_personal' => $historia->amputacion_pie_diabetico_personal,
                'obs_personal_amputacion_pie_diabetico' => $historia->obs_personal_amputacion_pie_diabetico,
                'enfermedad_pulmonar_personal' => $historia->enfermedad_pulmonar_personal,
                'obs_personal_enfermedad_pulmonar' => $historia->obs_personal_enfermedad_pulmonar,
                'victima_maltrato_personal' => $historia->victima_maltrato_personal,
                'obs_personal_maltrato_personal' => $historia->obs_personal_maltrato_personal,
                'antecedentes_quirurgicos' => $historia->antecedentes_quirurgicos,
                'obs_personal_antecedentes_quirurgicos' => $historia->obs_personal_antecedentes_quirurgicos,
                'acontosis_personal' => $historia->acontosis_personal,
                'obs_personal_acontosis' => $historia->obs_personal_acontosis,
                'otro_personal' => $historia->otro_personal,
                'obs_personal_otro' => $historia->obs_personal_otro,
                
                // ═══════════════════════════════════════════
                // 🔹 DISCAPACIDADES
                // ═══════════════════════════════════════════
                'discapacidad_fisica' => $historia->discapacidad_fisica,
                'discapacidad_visual' => $historia->discapacidad_visual,
                'discapacidad_mental' => $historia->discapacidad_mental,
                'discapacidad_auditiva' => $historia->discapacidad_auditiva,
                'discapacidad_intelectual' => $historia->discapacidad_intelectual,
                
                // ═══════════════════════════════════════════
                // 🔹 DROGODEPENDENCIA
                // ═══════════════════════════════════════════
                'drogo_dependiente' => $historia->drogo_dependiente,
                'drogo_dependiente_cual' => $historia->drogo_dependiente_cual,
                
                // ═══════════════════════════════════════════
                // 🔹 ANTECEDENTES FAMILIARES
                // ═══════════════════════════════════════════
                'hipertension_arterial' => $historia->hipertension_arterial,
                'parentesco_hipertension' => $historia->parentesco_hipertension,
                'diabetes_mellitus' => $historia->diabetes_mellitus,
                'parentesco_mellitus' => $historia->parentesco_mellitus,
                'artritis' => $historia->artritis,
                'parentesco_artritis' => $historia->parentesco_artritis,
                'enfermedad_cardiovascular' => $historia->enfermedad_cardiovascular,
                'parentesco_cardiovascular' => $historia->parentesco_cardiovascular,
                'antecedente_metabolico' => $historia->antecedente_metabolico,
                'parentesco_metabolico' => $historia->parentesco_metabolico,
                'cancer_mama_estomago_prostata_colon' => $historia->cancer_mama_estomago_prostata_colon,
                'parentesco_cancer' => $historia->parentesco_cancer,
                'leucemia' => $historia->leucemia,
                'parentesco_leucemia' => $historia->parentesco_leucemia,
                'vih' => $historia->vih,
                'parentesco_vih' => $historia->parentesco_vih,
                'otro' => $historia->otro,
                'parentesco_otro' => $historia->parentesco_otro,
                
                // ═══════════════════════════════════════════
                // 🔹 EDUCACIÓN EN SALUD
                // ═══════════════════════════════════════════
                'alimentacion' => $historia->alimentacion,
                'disminucion_consumo_sal_azucar' => $historia->disminucion_consumo_sal_azucar,
                'fomento_actividad_fisica' => $historia->fomento_actividad_fisica,
                'importancia_adherencia_tratamiento' => $historia->importancia_adherencia_tratamiento,
                'consumo_frutas_verduras' => $historia->consumo_frutas_verduras,
                'manejo_estres' => $historia->manejo_estres,
                'disminucion_consumo_cigarrillo' => $historia->disminucion_consumo_cigarrillo,
                'disminucion_peso' => $historia->disminucion_peso,
                
                // ═══════════════════════════════════════════
                // 🔹 OTROS CAMPOS
                // ═══════════════════════════════════════════
                'insulina_requiriente' => $historia->insulina_requiriente,
                'recibe_tratamiento_alternativo' => $historia->recibe_tratamiento_alternativo,
                'recibe_tratamiento_con_plantas_medicinales' => $historia->recibe_tratamiento_con_plantas_medicinales,
                'recibe_ritual_medicina_tradicional' => $historia->recibe_ritual_medicina_tradicional,
                'numero_frutas_diarias' => $historia->numero_frutas_diarias,
                'elevado_consumo_grasa_saturada' => $historia->elevado_consumo_grasa_saturada,
                'adiciona_sal_despues_preparar_comida' => $historia->adiciona_sal_despues_preparar_comida,
                
                // ═══════════════════════════════════════════
                // 🔹 REFORMULACIÓN
                // ═══════════════════════════════════════════
                'razon_reformulacion' => $historia->razon_reformulacion,
                'motivo_reformulacion' => $historia->motivo_reformulacion,
                'reformulacion_quien_reclama' => $historia->reformulacion_quien_reclama,
                'reformulacion_nombre_reclama' => $historia->reformulacion_nombre_reclama,
                'adicional' => $historia->adicional,
                
                // ═══════════════════════════════════════════
                // 🔹 OBSERVACIONES GENERALES
                // ═══════════════════════════════════════════
                'observaciones_generales' => $historia->observaciones_generales,
                
                // ═══════════════════════════════════════════
                // 🔹 DIAGNÓSTICOS
                // ═══════════════════════════════════════════
                'diagnosticos' => $historia->historiaDiagnosticos->map(function($item) {
                    return [
                        'uuid' => $item->uuid,
                        'tipo' => $item->tipo,
                        'tipo_diagnostico' => $item->tipo_diagnostico,
                        'diagnostico' => [
                            'uuid' => $item->diagnostico->uuid ?? $item->diagnostico->id,
                            'codigo' => $item->diagnostico->codigo ?? 'N/A',
                            'nombre' => $item->diagnostico->nombre ?? 'N/A',
                        ]
                    ];
                }),
                
                // ═══════════════════════════════════════════
                // 🔹 MEDICAMENTOS
                // ═══════════════════════════════════════════
                'medicamentos' => $historia->historiaMedicamentos->map(function($item) {
                    return [
                        'uuid' => $item->uuid,
                        'cantidad' => $item->cantidad,
                        'dosis' => $item->dosis,
                        'medicamento' => [
                            'uuid' => $item->medicamento->uuid ?? $item->medicamento->id,
                            'nombre' => $item->medicamento->nombre ?? 'N/A',
                            'principio_activo' => $item->medicamento->principio_activo ?? '',
                        ]
                    ];
                }),
                
                // ═══════════════════════════════════════════
                // 🔹 REMISIONES
                // ═══════════════════════════════════════════
                'remisiones' => $historia->historiaRemisiones->map(function($item) {
                    return [
                        'uuid' => $item->uuid,
                        'observacion' => $item->observacion,
                        'remision' => [
                            'uuid' => $item->remision->uuid ?? $item->remision->id,
                            'nombre' => $item->remision->nombre ?? 'N/A',
                            'tipo' => $item->remision->tipo ?? '',
                        ]
                    ];
                }),
                
                // ═══════════════════════════════════════════
                // 🔹 CUPS
                // ═══════════════════════════════════════════
                'cups' => $historia->historiaCups->map(function($item) {
                    return [
                        'uuid' => $item->uuid,
                        'observacion' => $item->observacion,
                        'cups' => [
                            'uuid' => $item->cups->uuid ?? $item->cups->id,
                            'codigo' => $item->cups->codigo ?? 'N/A',
                            'nombre' => $item->cups->nombre ?? 'N/A',
                        ]
                    ];
                }),
                
                // ═══════════════════════════════════════════
                // 🔹 COMPLEMENTARIA (SI EXISTE)
                // ═══════════════════════════════════════════
                'complementaria' => $historia->complementaria ? [
                    'uuid' => $historia->complementaria->uuid,
                    
                    // ✅ FISIOTERAPIA
                    'actitud' => $historia->complementaria->actitud,
                    'evaluacion_d' => $historia->complementaria->evaluacion_d,
                    'evaluacion_p' => $historia->complementaria->evaluacion_p,
                    'estado' => $historia->complementaria->estado,
                    'evaluacion_dolor' => $historia->complementaria->evaluacion_dolor,
                    'evaluacion_os' => $historia->complementaria->evaluacion_os,
                    'evaluacion_neu' => $historia->complementaria->evaluacion_neu,
                    'comitante' => $historia->complementaria->comitante,
                    'plan_seguir' => $historia->complementaria->plan_seguir,
                    
                    // ✅ PSICOLOGÍA
                    'estructura_familiar' => $historia->complementaria->estructura_familiar,
                    'psicologia_red_apoyo' => $historia->complementaria->psicologia_red_apoyo,
                    'psicologia_comportamiento_consulta' => $historia->complementaria->psicologia_comportamiento_consulta,
                    'psicologia_tratamiento_actual_adherencia' => $historia->complementaria->psicologia_tratamiento_actual_adherencia,
                    'psicologia_descripcion_problema' => $historia->complementaria->psicologia_descripcion_problema,
                    'analisis_conclusiones' => $historia->complementaria->analisis_conclusiones,
                    'psicologia_plan_intervencion_recomendacion' => $historia->complementaria->psicologia_plan_intervencion_recomendacion,
                    'avance_paciente' => $historia->complementaria->avance_paciente,
                    
                    // ✅ NUTRICIÓN - PRIMERA VEZ
                    'enfermedad_diagnostica' => $historia->complementaria->enfermedad_diagnostica,
                    'habito_intestinal' => $historia->complementaria->habito_intestinal,
                    'quirurgicos' => $historia->complementaria->quirurgicos,
                    'quirurgicos_observaciones' => $historia->complementaria->quirurgicos_observaciones,
                    'alergicos' => $historia->complementaria->alergicos,
                    'alergicos_observaciones' => $historia->complementaria->alergicos_observaciones,
                    'familiares' => $historia->complementaria->familiares,
                    'familiares_observaciones' => $historia->complementaria->familiares_observaciones,
                    'psa' => $historia->complementaria->psa,
                    'psa_observaciones' => $historia->complementaria->psa_observaciones,
                    'farmacologicos' => $historia->complementaria->farmacologicos,
                    'farmacologicos_observaciones' => $historia->complementaria->farmacologicos_observaciones,
                    'sueno' => $historia->complementaria->sueno,
                    'sueno_observaciones' => $historia->complementaria->sueno_observaciones,
                    'tabaquismo_observaciones' => $historia->complementaria->tabaquismo_observaciones,
                    'ejercicio' => $historia->complementaria->ejercicio,
                    'ejercicio_observaciones' => $historia->complementaria->ejercicio_observaciones,
                    'metodo_conceptivo' => $historia->complementaria->metodo_conceptivo,
                    'metodo_conceptivo_cual' => $historia->complementaria->metodo_conceptivo_cual,
                    'embarazo_actual' => $historia->complementaria->embarazo_actual,
                    'semanas_gestacion' => $historia->complementaria->semanas_gestacion,
                    'climatero' => $historia->complementaria->climatero,
                    'tolerancia_via_oral' => $historia->complementaria->tolerancia_via_oral,
                    'percepcion_apetito' => $historia->complementaria->percepcion_apetito,
                    'percepcion_apetito_observacion' => $historia->complementaria->percepcion_apetito_observacion,
                    'alimentos_preferidos' => $historia->complementaria->alimentos_preferidos,
                    'alimentos_rechazados' => $historia->complementaria->alimentos_rechazados,
                    'suplemento_nutricionales' => $historia->complementaria->suplemento_nutricionales,
                    'dieta_especial' => $historia->complementaria->dieta_especial,
                    'dieta_especial_cual' => $historia->complementaria->dieta_especial_cual,
                    'desayuno_hora' => $historia->complementaria->desayuno_hora,
                    'desayuno_hora_observacion' => $historia->complementaria->desayuno_hora_observacion,
                    'media_manana_hora' => $historia->complementaria->media_manana_hora,
                    'media_manana_hora_observacion' => $historia->complementaria->media_manana_hora_observacion,
                    'almuerzo_hora' => $historia->complementaria->almuerzo_hora,
                    'almuerzo_hora_observacion' => $historia->complementaria->almuerzo_hora_observacion,
                    'media_tarde_hora' => $historia->complementaria->media_tarde_hora,
                    'media_tarde_hora_observacion' => $historia->complementaria->media_tarde_hora_observacion,
                    'cena_hora' => $historia->complementaria->cena_hora,
                    'cena_hora_observacion' => $historia->complementaria->cena_hora_observacion,
                    'refrigerio_nocturno_hora' => $historia->complementaria->refrigerio_nocturno_hora,
                    'refrigerio_nocturno_hora_observacion' => $historia->complementaria->refrigerio_nocturno_hora_observacion,
                    'peso_ideal' => $historia->complementaria->peso_ideal,
                    'interpretacion' => $historia->complementaria->interpretacion,
                    'meta_meses' => $historia->complementaria->meta_meses,
                    'analisis_nutricional' => $historia->complementaria->analisis_nutricional,
                    
                    // ✅ NUTRICIÓN - CONTROL
                    'comida_desayuno' => $historia->complementaria->comida_desayuno,
                    'comida_medio_desayuno' => $historia->complementaria->comida_medio_desayuno,
                    'comida_almuerzo' => $historia->complementaria->comida_almuerzo,
                    'comida_medio_almuerzo' => $historia->complementaria->comida_medio_almuerzo,
                    'comida_cena' => $historia->complementaria->comida_cena,
                    'lacteo' => $historia->complementaria->lacteo,
                    'lacteo_observacion' => $historia->complementaria->lacteo_observacion,
                    'huevo' => $historia->complementaria->huevo,
                    'huevo_observacion' => $historia->complementaria->huevo_observacion,
                    'embutido' => $historia->complementaria->embutido,
                    'embutido_observacion' => $historia->complementaria->embutido_observacion,
                    'carne_roja' => $historia->complementaria->carne_roja,
                    'carne_blanca' => $historia->complementaria->carne_blanca,
                    'carne_vicera' => $historia->complementaria->carne_vicera,
                    'carne_observacion' => $historia->complementaria->carne_observacion,
                    'leguminosas' => $historia->complementaria->leguminosas,
                    'leguminosas_observacion' => $historia->complementaria->leguminosas_observacion,
                    'frutas_jugo' => $historia->complementaria->frutas_jugo,
                    'frutas_porcion' => $historia->complementaria->frutas_porcion,
                    'frutas_observacion' => $historia->complementaria->frutas_observacion,
                    'verduras_hortalizas' => $historia->complementaria->verduras_hortalizas,
                    'vh_observacion' => $historia->complementaria->vh_observacion,
                    'cereales' => $historia->complementaria->cereales,
                    'cereales_observacion' => $historia->complementaria->cereales_observacion,
                    'rtp' => $historia->complementaria->rtp,
                    'rtp_observacion' => $historia->complementaria->rtp_observacion,
                    'azucar_dulce' => $historia->complementaria->azucar_dulce,
                    'ad_observacion' => $historia->complementaria->ad_observacion,
                    'diagnostico_nutri' => $historia->complementaria->diagnostico_nutri,
                    'plan_seguir_nutri' => $historia->complementaria->plan_seguir_nutri,
                    
                    // ✅ INTERNISTA/NEFROLOGÍA
                    'descripcion_sistema_nervioso' => $historia->complementaria->descripcion_sistema_nervioso,
                    'sistema_hemolinfatico' => $historia->complementaria->sistema_hemolinfatico,
                    'descripcion_sistema_hemolinfatico' => $historia->complementaria->descripcion_sistema_hemolinfatico,
                    'aparato_digestivo' => $historia->complementaria->aparato_digestivo,
                    'descripcion_aparato_digestivo' => $historia->complementaria->descripcion_aparato_digestivo,
                    'organo_sentido' => $historia->complementaria->organo_sentido,
                    'descripcion_organos_sentidos' => $historia->complementaria->descripcion_organos_sentidos,
                    'endocrino_metabolico' => $historia->complementaria->endocrino_metabolico,
                    'descripcion_endocrino_metabolico' => $historia->complementaria->descripcion_endocrino_metabolico,
                    'inmunologico' => $historia->complementaria->inmunologico,
                    'descripcion_inmunologico' => $historia->complementaria->descripcion_inmunologico,
                    'cancer_tumores_radioterapia_quimio' => $historia->complementaria->cancer_tumores_radioterapia_quimio,
                    'descripcion_cancer_tumores_radio_quimioterapia' => $historia->complementaria->descripcion_cancer_tumores_radio_quimioterapia,
                    'glandula_mamaria' => $historia->complementaria->glandula_mamaria,
                    'descripcion_glandulas_mamarias' => $historia->complementaria->descripcion_glandulas_mamarias,
                    'hipertension_diabetes_erc' => $historia->complementaria->hipertension_diabetes_erc,
                    'descripcion_hipertension_diabetes_erc' => $historia->complementaria->descripcion_hipertension_diabetes_erc,
                    'reacciones_alergica' => $historia->complementaria->reacciones_alergica,
                    'descripcion_reacion_alergica' => $historia->complementaria->descripcion_reacion_alergica,
                    'cardio_vasculares' => $historia->complementaria->cardio_vasculares,
                    'descripcion_cardio_vasculares' => $historia->complementaria->descripcion_cardio_vasculares,
                    'respiratorios' => $historia->complementaria->respiratorios,
                    'descripcion_respiratorios' => $historia->complementaria->descripcion_respiratorios,
                    'urinarias' => $historia->complementaria->urinarias,
                    'descripcion_urinarias' => $historia->complementaria->descripcion_urinarias,
                    'osteoarticulares' => $historia->complementaria->osteoarticulares,
                    'descripcion_osteoarticulares' => $historia->complementaria->descripcion_osteoarticulares,
                    'infecciosos' => $historia->complementaria->infecciosos,
                    'descripcion_infecciosos' => $historia->complementaria->descripcion_infecciosos,
                    'cirugia_trauma' => $historia->complementaria->cirugia_trauma,
                    'descripcion_cirugias_traumas' => $historia->complementaria->descripcion_cirugias_traumas,
                    'tratamiento_medicacion' => $historia->complementaria->tratamiento_medicacion,
                    'descripcion_tratamiento_medicacion' => $historia->complementaria->descripcion_tratamiento_medicacion,
                    'antecedente_quirurgico' => $historia->complementaria->antecedente_quirurgico,
                    'descripcion_antecedentes_quirurgicos' => $historia->complementaria->descripcion_antecedentes_quirurgicos,
                    'antecedentes_familiares' => $historia->complementaria->antecedentes_familiares,
                    'descripcion_antecedentes_familiares' => $historia->complementaria->descripcion_antecedentes_familiares,
                    'consumo_tabaco' => $historia->complementaria->consumo_tabaco,
                    'descripcion_consumo_tabaco' => $historia->complementaria->descripcion_consumo_tabaco,
                    'antecedentes_alcohol' => $historia->complementaria->antecedentes_alcohol,
                    'descripcion_antecedentes_alcohol' => $historia->complementaria->descripcion_antecedentes_alcohol,
                    'sedentarismo' => $historia->complementaria->sedentarismo,
                    'descripcion_sedentarismo' => $historia->complementaria->descripcion_sedentarismo,
                    'ginecologico' => $historia->complementaria->ginecologico,
                    'descripcion_ginecologicos' => $historia->complementaria->descripcion_ginecologicos,
                    'citologia_vaginal' => $historia->complementaria->citologia_vaginal,
                    'descripcion_citologia_vaginal' => $historia->complementaria->descripcion_citologia_vaginal,
                    'menarquia' => $historia->complementaria->menarquia,
                    'gestaciones' => $historia->complementaria->gestaciones,
                    'parto' => $historia->complementaria->parto,
                    'aborto' => $historia->complementaria->aborto,
                    'cesaria' => $historia->complementaria->cesaria,
                    'antecedente_personal' => $historia->complementaria->antecedente_personal,
                    'neurologico_estado_mental' => $historia->complementaria->neurologico_estado_mental,
                    'obs_neurologico_estado_mental' => $historia->complementaria->obs_neurologico_estado_mental,
                ] : null,
            ];

            Log::info('✅ Historia clínica completa procesada', [
                'historia_uuid' => $historia->uuid,
                'campos_totales' => count($historiaCompleta),
                'diagnosticos_count' => $historiaCompleta['diagnosticos']->count(),
                'medicamentos_count' => $historiaCompleta['medicamentos']->count(),
                'remisiones_count' => $historiaCompleta['remisiones']->count(),
                'cups_count' => $historiaCompleta['cups']->count(),
                'tiene_complementaria' => $historiaCompleta['complementaria'] !== null
            ]);

            return response()->json([
                'success' => true,
                'data' => $historiaCompleta,
                'message' => 'Historia clínica completa obtenida exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Historia clínica no encontrada', [
                'historia_uuid' => $uuid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Historia clínica no encontrada'
            ], 404);

        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo historia clínica completa', [
                'error' => $e->getMessage(),
                'historia_uuid' => $uuid,
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historia clínica completa',
                'error' => $e->getMessage()
            ], 500);
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
 public function determinarVistaHistoriaClinica(Request $request, string $citaUuid)
{
    try {
        Log::info('🔍 ===== INICIO: Determinando vista de historia clínica =====', [
            'cita_uuid' => $citaUuid,
            'timestamp' => now()->toDateTimeString()
        ]);

        // ✅ PASO 1: Obtener la cita (SIN historiaClinica)
        Log::info('🔍 PASO 1: Buscando cita...');
        $cita = \App\Models\Cita::with([
            'paciente',
            'agenda.usuarioMedico.especialidad'
        ])->where('uuid', $citaUuid)->first();

        if (!$cita) {
            Log::error('❌ Cita no encontrada', ['cita_uuid' => $citaUuid]);
            return response()->json([
                'error' => 'Cita no encontrada'
            ], 404);
        }

        Log::info('✅ PASO 1: Cita encontrada', [
            'cita_id' => $cita->id,
            'paciente_uuid' => $cita->paciente_uuid,
            'paciente_nombre' => $cita->paciente->nombre_completo ?? 'N/A'
        ]);

        // ✅ PASO 2: Obtener especialidad
        Log::info('🔍 PASO 2: Obteniendo especialidad...');
        $especialidad = $cita->agenda->usuarioMedico->especialidad->nombre ?? null;

        if (!$especialidad) {
            Log::error('❌ Especialidad no encontrada', [
                'cita_id' => $cita->id,
                'agenda_id' => $cita->agenda_id ?? 'N/A'
            ]);
            return response()->json([
                'error' => 'Especialidad no encontrada'
            ], 404);
        }

        Log::info('✅ PASO 2: Especialidad obtenida', [
            'especialidad' => $especialidad,
            'medico' => $cita->agenda->usuarioMedico->nombre_completo ?? 'N/A'
        ]);

        // 🔥 PASO 3: VERIFICAR SI ES PRIMERA VEZ O CONTROL
        Log::info('🔍 PASO 3: Verificando tipo de consulta (PRIMERA VEZ o CONTROL)...');
        
        $esPrimeraVez = $this->esPrimeraConsultaDeEspecialidad($cita->paciente_uuid, $especialidad, $cita->id);
        $tipoConsulta = $esPrimeraVez ? 'PRIMERA VEZ' : 'CONTROL';

        Log::info('✅ PASO 3: Tipo de consulta determinado', [
            'es_primera_vez' => $esPrimeraVez,
            'tipo_consulta' => $tipoConsulta,
            'logica' => $esPrimeraVez ? '0 historias → PRIMERA VEZ' : '>0 historias → CONTROL'
        ]);

        // ✅✅✅ PASO 4: Obtener historia previa (SIEMPRE - tanto PRIMERA VEZ como CONTROL) ✅✅✅
        Log::info('🔍 PASO 4: Obteniendo historia previa (para ambos tipos de consulta)...');
        $historiaPreviaData = $this->obtenerUltimaHistoriaPorEspecialidad($cita->paciente_uuid, $especialidad);

        if ($historiaPreviaData) {
            Log::info('✅ PASO 4: Historia previa obtenida', [
                'tipo_consulta' => $tipoConsulta,
                'es_primera_vez' => $esPrimeraVez,
                'medicamentos_count' => count($historiaPreviaData['medicamentos'] ?? []),
                'diagnosticos_count' => count($historiaPreviaData['diagnosticos'] ?? []),
                'remisiones_count' => count($historiaPreviaData['remisiones'] ?? []),
                'cups_count' => count($historiaPreviaData['cups'] ?? []),
                'tiene_clasificaciones' => !empty($historiaPreviaData['clasificacion_estado_metabolico']),
                'tiene_talla' => !empty($historiaPreviaData['talla'])
            ]);
        } else {
            Log::info('ℹ️ PASO 4: No se encontró historia previa', [
                'tipo_consulta' => $tipoConsulta,
                'es_primera_vez' => $esPrimeraVez,
                'razon' => 'Paciente nuevo o sin historias anteriores'
            ]);
        }

        // ✅ PASO 5: Determinar vista según especialidad
        Log::info('🔍 PASO 5: Determinando vista según especialidad...');
        $vistaInfo = $this->determinarVistaSegunEspecialidad($especialidad, $tipoConsulta);

        Log::info('✅ PASO 5: Vista determinada', [
            'vista' => $vistaInfo['vista'] ?? 'N/A',
            'usa_complementaria' => $vistaInfo['usa_complementaria'] ?? false,
            'solo_control' => $vistaInfo['solo_control'] ?? false
        ]);

        // ✅ RESPUESTA FINAL
        Log::info('✅ ===== FIN: Vista determinada exitosamente =====', [
            'especialidad' => $especialidad,
            'tipo_consulta' => $tipoConsulta,
            'es_primera_vez' => $esPrimeraVez,
            'vista' => $vistaInfo['vista'] ?? 'N/A',
            'tiene_datos_previos' => !is_null($historiaPreviaData)
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'cita' => $cita,
                'especialidad' => $especialidad,
                'tipo_consulta' => $tipoConsulta,
                'es_primera_vez' => $esPrimeraVez,
                'vista_recomendada' => $vistaInfo,
                'historia_previa' => $historiaPreviaData // ✅ SIEMPRE SE ENVÍA (puede ser null)
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('❌ ===== ERROR: Determinando vista de historia clínica =====', [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'Error al determinar la vista de historia clínica',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * ✅ VERIFICAR HISTORIAS ANTERIORES - FILTRADO POR ESPECIALIDAD
 */
private function verificarHistoriasAnterioresPorEspecialidad(string $pacienteUuid, string $especialidad): bool
{
    try {
        Log::info('🔍 Verificando historias anteriores POR ESPECIALIDAD (solo para contar)', [
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

        // ✅ PASO 2: Buscar citas usando PACIENTE_UUID
        $citasDelPaciente = \App\Models\Cita::where('paciente_uuid', $paciente->uuid)
            ->where('estado', '!=', 'CANCELADA')
            ->get();

        Log::info('🔍 Citas del paciente encontradas', [
            'paciente_uuid' => $paciente->uuid,
            'total_citas' => $citasDelPaciente->count(),
            'citas_ids' => $citasDelPaciente->pluck('id')->toArray()
        ]);

        if ($citasDelPaciente->isEmpty()) {
            Log::info('ℹ️ Paciente no tiene citas - PRIMERA VEZ', [
                'paciente_uuid' => $paciente->uuid
            ]);
            return false;
        }

        // ✅ PASO 3: Buscar historias DE ESA ESPECIALIDAD ESPECÍFICA (SOLO PARA CONTAR)
        $citasIds = $citasDelPaciente->pluck('id')->toArray();
        
        // 🔥 FILTRAR POR ESPECIALIDAD (SOLO PARA DETERMINAR PRIMERA VEZ O CONTROL)
        $historiasDeEspecialidad = \App\Models\HistoriaClinica::whereIn('cita_id', $citasIds)
            ->whereHas('cita.agenda.usuarioMedico.especialidad', function($query) use ($especialidad) {
                $query->where('nombre', $especialidad);
            })
            ->get();

        Log::info('🔍 Historias DE LA ESPECIALIDAD encontradas (solo para contar)', [
            'paciente_uuid' => $paciente->uuid,
            'especialidad' => $especialidad,
            'total_historias_especialidad' => $historiasDeEspecialidad->count(),
            'historias_ids' => $historiasDeEspecialidad->pluck('id')->toArray()
        ]);

        // ✅ PASO 4: Determinar tipo de consulta
        $tieneHistoriasDeEspecialidad = $historiasDeEspecialidad->count() > 0;
        $tipoConsulta = $tieneHistoriasDeEspecialidad ? 'CONTROL' : 'PRIMERA VEZ';

        Log::info('✅ RESULTADO FINAL: Contador por especialidad', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'total_historias_especialidad' => $historiasDeEspecialidad->count(),
            'tiene_historias_especialidad' => $tieneHistoriasDeEspecialidad,
            'tipo_consulta' => $tipoConsulta,
            'nota' => 'Los datos se cargarán de CUALQUIER especialidad'
        ]);

        return $tieneHistoriasDeEspecialidad;

    } catch (\Exception $e) {
        Log::error('❌ Error verificando historias por especialidad', [
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
 * ✅ OBTENER ÚLTIMA HISTORIA - FILTRADO POR ESPECIALIDAD
 */
private function obtenerUltimaHistoriaPorEspecialidad(string $pacienteUuid, string $especialidad): ?array
{
    try {
        Log::info('🔍 Obteniendo última historia DE CUALQUIER ESPECIALIDAD (para cargar datos)', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad_actual' => $especialidad,
            'nota' => 'Se busca en TODAS las especialidades'
        ]);

        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            Log::warning('⚠️ Paciente no encontrado', ['paciente_uuid' => $pacienteUuid]);
            return null;
        }

        // 🔥 BUSCAR LA ÚLTIMA HISTORIA DE CUALQUIER ESPECIALIDAD (SIN FILTRO)
        $ultimaHistoria = \App\Models\HistoriaClinica::with([
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups',
            'cita.agenda.usuarioMedico.especialidad' // ✅ Para saber de qué especialidad viene
        ])
        ->whereHas('cita', function($query) use ($paciente) {
            $query->where('paciente_uuid', $paciente->uuid);
        })
        // ❌ SIN FILTRO DE ESPECIALIDAD - BUSCA EN TODAS
        ->orderBy('created_at', 'desc')
        ->first();

        if (!$ultimaHistoria) {
            Log::info('ℹ️ No se encontró historia previa de ninguna especialidad', [
                'paciente_uuid' => $paciente->uuid,
                'especialidad_actual' => $especialidad
            ]);
            return null;
        }

        $especialidadOrigen = $ultimaHistoria->cita->agenda->usuarioMedico->especialidad->nombre ?? 'DESCONOCIDA';

        Log::info('✅ Historia encontrada (puede ser de otra especialidad)', [
            'historia_uuid' => $ultimaHistoria->uuid,
            'especialidad_origen' => $especialidadOrigen,
            'especialidad_actual' => $especialidad,
            'es_misma_especialidad' => $especialidadOrigen === $especialidad,
            'created_at' => $ultimaHistoria->created_at
        ]);

        // ✅ FORMATEAR LA HISTORIA BASE
        $historiaFormateada = $this->procesarHistoriaParaFrontend($ultimaHistoria);

        Log::info('📊 Historia procesada (de cualquier especialidad)', [
            'historia_uuid' => $ultimaHistoria->uuid,
            'especialidad_origen' => $especialidadOrigen,
            'especialidad_actual' => $especialidad,
            'medicamentos_count' => count($historiaFormateada['medicamentos'] ?? []),
            'diagnosticos_count' => count($historiaFormateada['diagnosticos'] ?? []),
            'clasificacion_metabolica' => $historiaFormateada['clasificacion_estado_metabolico'] ?? 'vacío'
        ]);

        // ✅ COMPLETAR DATOS FALTANTES (DE CUALQUIER ESPECIALIDAD)
        $historiaFormateada = $this->completarDatosFaltantesDeCualquierEspecialidad($paciente->uuid, $historiaFormateada);

        Log::info('✅ Historia COMPLETA después de rellenar (de todas las especialidades)', [
            'especialidad_actual' => $especialidad,
            'medicamentos_final' => count($historiaFormateada['medicamentos'] ?? []),
            'diagnosticos_final' => count($historiaFormateada['diagnosticos'] ?? []),
            'clasificacion_final' => $historiaFormateada['clasificacion_estado_metabolico'] ?? 'vacío'
        ]);

        return $historiaFormateada;

    } catch (\Exception $e) {
        Log::error('❌ Error obteniendo última historia', [
            'error' => $e->getMessage(),
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'line' => $e->getLine()
        ]);
        
        return null;
    }
}
/**
 * ✅ COMPLETAR DATOS FALTANTES - FILTRADO POR ESPECIALIDAD
 */
private function completarDatosFaltantesDeCualquierEspecialidad(string $pacienteUuid, array $historiaBase): array
{
    try {
        Log::info('🔍 Buscando datos faltantes en historias DE CUALQUIER ESPECIALIDAD', [
            'paciente_uuid' => $pacienteUuid,
            'nota' => 'Se busca en TODAS las especialidades'
        ]);

        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            return $historiaBase;
        }

        // ✅ IDENTIFICAR CAMPOS VACÍOS
        $camposPorCompletar = [
            // RELACIONES
            'medicamentos' => empty($historiaBase['medicamentos']),
            'remisiones' => empty($historiaBase['remisiones']),
            'cups' => empty($historiaBase['cups']),
            
            // CLASIFICACIONES
            'clasificacion_estado_metabolico' => empty($historiaBase['clasificacion_estado_metabolico']),
            'clasificacion_hta' => empty($historiaBase['clasificacion_hta']),
            'clasificacion_dm' => empty($historiaBase['clasificacion_dm']),
            'clasificacion_rcv' => empty($historiaBase['clasificacion_rcv']),
            'clasificacion_erc_estado' => empty($historiaBase['clasificacion_erc_estado']),
            'clasificacion_erc_categoria_ambulatoria_persistente' => empty($historiaBase['clasificacion_erc_categoria_ambulatoria_persistente']),
            
            // TASAS DE FILTRACIÓN
            'tasa_filtracion_glomerular_ckd_epi' => empty($historiaBase['tasa_filtracion_glomerular_ckd_epi']),
            'tasa_filtracion_glomerular_gockcroft_gault' => empty($historiaBase['tasa_filtracion_glomerular_gockcroft_gault']),
            
            // ANTECEDENTES PERSONALES
            'hipertension_arterial_personal' => ($historiaBase['hipertension_arterial_personal'] ?? 'NO') === 'NO',
            'diabetes_mellitus_personal' => ($historiaBase['diabetes_mellitus_personal'] ?? 'NO') === 'NO',
            
            // TALLA
            'talla' => empty($historiaBase['talla']),
            
            // TEST DE MORISKY
            'test_morisky_olvida_tomar_medicamentos' => empty($historiaBase['test_morisky_olvida_tomar_medicamentos']),
            'test_morisky_toma_medicamentos_hora_indicada' => empty($historiaBase['test_morisky_toma_medicamentos_hora_indicada']),
            'test_morisky_cuando_esta_bien_deja_tomar_medicamentos' => empty($historiaBase['test_morisky_cuando_esta_bien_deja_tomar_medicamentos']),
            'test_morisky_siente_mal_deja_tomarlos' => empty($historiaBase['test_morisky_siente_mal_deja_tomarlos']),
            'test_morisky_valoracio_psicologia' => empty($historiaBase['test_morisky_valoracio_psicologia']),
            'adherente' => empty($historiaBase['adherente']),
            
            // EDUCACIÓN EN SALUD
            'alimentacion' => empty($historiaBase['alimentacion']),
            'disminucion_consumo_sal_azucar' => empty($historiaBase['disminucion_consumo_sal_azucar']),
            'fomento_actividad_fisica' => empty($historiaBase['fomento_actividad_fisica']),
            'importancia_adherencia_tratamiento' => empty($historiaBase['importancia_adherencia_tratamiento']),
            'consumo_frutas_verduras' => empty($historiaBase['consumo_frutas_verduras']),
            'manejo_estres' => empty($historiaBase['manejo_estres']),
            'disminucion_consumo_cigarrillo' => empty($historiaBase['disminucion_consumo_cigarrillo']),
            'disminucion_peso' => empty($historiaBase['disminucion_peso']),
        ];

        Log::info('📋 Campos a completar', [
            'total_vacios' => count(array_filter($camposPorCompletar)),
            'campos' => array_keys(array_filter($camposPorCompletar))
        ]);

        // ✅ SI TODO ESTÁ LLENO, RETORNAR
        if (!in_array(true, $camposPorCompletar)) {
            Log::info('✅ Todos los campos están completos, no es necesario buscar');
            return $historiaBase;
        }

        // 🔥 BUSCAR EN HISTORIAS ANTERIORES DE CUALQUIER ESPECIALIDAD (SIN FILTRO)
        $historiasAnteriores = \App\Models\HistoriaClinica::with([
            'historiaDiagnosticos.diagnostico',
            'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision',
            'historiaCups.cups',
            'cita.agenda.usuarioMedico.especialidad' // ✅ Para saber de qué especialidad viene
        ])
        ->whereHas('cita', function($query) use ($paciente) {
            $query->where('paciente_uuid', $paciente->uuid);
        })
        // ❌ SIN FILTRO DE ESPECIALIDAD - BUSCA EN TODAS
        ->orderBy('created_at', 'desc')
        ->skip(1) // SALTAR LA PRIMERA (YA LA TENEMOS)
        ->take(20) // REVISAR ÚLTIMAS 20 HISTORIAS
        ->get();

        Log::info('🔍 Historias anteriores DE TODAS LAS ESPECIALIDADES encontradas', [
            'count' => $historiasAnteriores->count(),
            'especialidades' => $historiasAnteriores->map(function($h) {
                return $h->cita->agenda->usuarioMedico->especialidad->nombre ?? 'N/A';
            })->unique()->values()->toArray()
        ]);

        // ✅ RECORRER HISTORIAS Y COMPLETAR DATOS
        foreach ($historiasAnteriores as $historia) {
            
            $especialidadHistoria = $historia->cita->agenda->usuarioMedico->especialidad->nombre ?? 'DESCONOCIDA';
            
            Log::info('🔍 Revisando historia de cualquier especialidad', [
                'historia_uuid' => $historia->uuid,
                'especialidad' => $especialidadHistoria,
                'created_at' => $historia->created_at
            ]);

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR MEDICAMENTOS
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['medicamentos'] && $historia->historiaMedicamentos && $historia->historiaMedicamentos->isNotEmpty()) {
                $medicamentos = [];
                foreach ($historia->historiaMedicamentos as $item) {
                    if ($item->medicamento) {
                        $medicamentos[] = [
                            'uuid' => $item->uuid ?? null,
                            'medicamento_id' => $item->medicamento->uuid ?? $item->medicamento->id,
                            'cantidad' => $item->cantidad ?? '1',
                            'dosis' => $item->dosis ?? 'Según indicación',
                            'medicamento' => [
                                'uuid' => $item->medicamento->uuid ?? $item->medicamento->id,
                                'id' => $item->medicamento->id,
                                'nombre' => $item->medicamento->nombre ?? 'Sin nombre',
                                'principio_activo' => $item->medicamento->principio_activo ?? ''
                            ]
                        ];
                    }
                }
                if (!empty($medicamentos)) {
                    $historiaBase['medicamentos'] = $medicamentos;
                    $camposPorCompletar['medicamentos'] = false;
                    Log::info('✅ Medicamentos completados desde especialidad', [
                        'historia_uuid' => $historia->uuid,
                        'especialidad_origen' => $especialidadHistoria,
                        'count' => count($medicamentos)
                    ]);
                }
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR REMISIONES
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['remisiones'] && $historia->historiaRemisiones && $historia->historiaRemisiones->isNotEmpty()) {
                $remisiones = [];
                foreach ($historia->historiaRemisiones as $item) {
                    if ($item->remision) {
                        $remisiones[] = [
                            'uuid' => $item->uuid ?? null,
                            'remision_id' => $item->remision->uuid ?? $item->remision->id,
                            'observacion' => $item->observacion ?? '',
                            'remision' => [
                                'uuid' => $item->remision->uuid ?? $item->remision->id,
                                'id' => $item->remision->id,
                                'nombre' => $item->remision->nombre ?? 'Sin nombre',
                                'tipo' => $item->remision->tipo ?? ''
                            ]
                        ];
                    }
                }
                if (!empty($remisiones)) {
                    $historiaBase['remisiones'] = $remisiones;
                    $camposPorCompletar['remisiones'] = false;
                    Log::info('✅ Remisiones completadas desde especialidad', [
                        'historia_uuid' => $historia->uuid,
                        'especialidad_origen' => $especialidadHistoria
                    ]);
                }
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR CUPS
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['cups'] && $historia->historiaCups && $historia->historiaCups->isNotEmpty()) {
                $cups = [];
                foreach ($historia->historiaCups as $item) {
                    if ($item->cups) {
                        $cups[] = [
                            'uuid' => $item->uuid ?? null,
                            'cups_id' => $item->cups->uuid ?? $item->cups->id,
                            'observacion' => $item->observacion ?? '',
                            'cups' => [
                                'uuid' => $item->cups->uuid ?? $item->cups->id,
                                'id' => $item->cups->id,
                                'codigo' => $item->cups->codigo ?? 'Sin código',
                                'nombre' => $item->cups->nombre ?? 'Sin nombre'
                            ]
                        ];
                    }
                }
                if (!empty($cups)) {
                    $historiaBase['cups'] = $cups;
                    $camposPorCompletar['cups'] = false;
                    Log::info('✅ CUPS completados desde especialidad', [
                        'historia_uuid' => $historia->uuid,
                        'especialidad_origen' => $especialidadHistoria
                    ]);
                }
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR CLASIFICACIONES
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['clasificacion_estado_metabolico'] && !empty($historia->clasificacion_estado_metabolico)) {
                $historiaBase['clasificacion_estado_metabolico'] = $historia->clasificacion_estado_metabolico;
                $camposPorCompletar['clasificacion_estado_metabolico'] = false;
                Log::info('✅ Clasificación metabólica desde especialidad', [
                    'especialidad_origen' => $especialidadHistoria
                ]);
            }

            if ($camposPorCompletar['clasificacion_hta'] && !empty($historia->clasificacion_hta)) {
                $historiaBase['clasificacion_hta'] = $historia->clasificacion_hta;
                $camposPorCompletar['clasificacion_hta'] = false;
            }

            if ($camposPorCompletar['clasificacion_dm'] && !empty($historia->clasificacion_dm)) {
                $historiaBase['clasificacion_dm'] = $historia->clasificacion_dm;
                $camposPorCompletar['clasificacion_dm'] = false;
            }

            if ($camposPorCompletar['clasificacion_rcv'] && !empty($historia->clasificacion_rcv)) {
                $historiaBase['clasificacion_rcv'] = $historia->clasificacion_rcv;
                $camposPorCompletar['clasificacion_rcv'] = false;
            }

            if ($camposPorCompletar['clasificacion_erc_estado'] && !empty($historia->clasificacion_erc_estado)) {
                $historiaBase['clasificacion_erc_estado'] = $historia->clasificacion_erc_estado;
                $camposPorCompletar['clasificacion_erc_estado'] = false;
            }

            if ($camposPorCompletar['clasificacion_erc_categoria_ambulatoria_persistente'] && !empty($historia->clasificacion_erc_categoria_ambulatoria_persistente)) {
                $historiaBase['clasificacion_erc_categoria_ambulatoria_persistente'] = $historia->clasificacion_erc_categoria_ambulatoria_persistente;
                $camposPorCompletar['clasificacion_erc_categoria_ambulatoria_persistente'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR TASAS DE FILTRACIÓN
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['tasa_filtracion_glomerular_ckd_epi'] && !empty($historia->tasa_filtracion_glomerular_ckd_epi)) {
                $historiaBase['tasa_filtracion_glomerular_ckd_epi'] = $historia->tasa_filtracion_glomerular_ckd_epi;
                $camposPorCompletar['tasa_filtracion_glomerular_ckd_epi'] = false;
            }

            if ($camposPorCompletar['tasa_filtracion_glomerular_gockcroft_gault'] && !empty($historia->tasa_filtracion_glomerular_gockcroft_gault)) {
                $historiaBase['tasa_filtracion_glomerular_gockcroft_gault'] = $historia->tasa_filtracion_glomerular_gockcroft_gault;
                $camposPorCompletar['tasa_filtracion_glomerular_gockcroft_gault'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR ANTECEDENTES PERSONALES
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['hipertension_arterial_personal'] && !empty($historia->hipertension_arterial_personal) && $historia->hipertension_arterial_personal !== 'NO') {
                $historiaBase['hipertension_arterial_personal'] = $historia->hipertension_arterial_personal;
                $historiaBase['obs_hipertension_arterial_personal'] = $historia->obs_personal_hipertension_arterial ?? null;
                $camposPorCompletar['hipertension_arterial_personal'] = false;
            }

            if ($camposPorCompletar['diabetes_mellitus_personal'] && !empty($historia->diabetes_mellitus_personal) && $historia->diabetes_mellitus_personal !== 'NO') {
                $historiaBase['diabetes_mellitus_personal'] = $historia->diabetes_mellitus_personal;
                $historiaBase['obs_diabetes_mellitus_personal'] = $historia->obs_personal_mellitus ?? null;
                $camposPorCompletar['diabetes_mellitus_personal'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR TALLA
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['talla'] && !empty($historia->talla)) {
                $historiaBase['talla'] = $historia->talla;
                $camposPorCompletar['talla'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR TEST DE MORISKY
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['test_morisky_olvida_tomar_medicamentos'] && !empty($historia->olvida_tomar_medicamentos)) {
                $historiaBase['test_morisky_olvida_tomar_medicamentos'] = $historia->olvida_tomar_medicamentos;
                $camposPorCompletar['test_morisky_olvida_tomar_medicamentos'] = false;
            }

            if ($camposPorCompletar['test_morisky_toma_medicamentos_hora_indicada'] && !empty($historia->toma_medicamentos_hora_indicada)) {
                $historiaBase['test_morisky_toma_medicamentos_hora_indicada'] = $historia->toma_medicamentos_hora_indicada;
                $camposPorCompletar['test_morisky_toma_medicamentos_hora_indicada'] = false;
            }

            if ($camposPorCompletar['test_morisky_cuando_esta_bien_deja_tomar_medicamentos'] && !empty($historia->cuando_esta_bien_deja_tomar_medicamentos)) {
                $historiaBase['test_morisky_cuando_esta_bien_deja_tomar_medicamentos'] = $historia->cuando_esta_bien_deja_tomar_medicamentos;
                $camposPorCompletar['test_morisky_cuando_esta_bien_deja_tomar_medicamentos'] = false;
            }

            if ($camposPorCompletar['test_morisky_siente_mal_deja_tomarlos'] && !empty($historia->siente_mal_deja_tomarlos)) {
                $historiaBase['test_morisky_siente_mal_deja_tomarlos'] = $historia->siente_mal_deja_tomarlos;
                $camposPorCompletar['test_morisky_siente_mal_deja_tomarlos'] = false;
            }

            if ($camposPorCompletar['test_morisky_valoracio_psicologia'] && !empty($historia->valoracion_psicologia)) {
                $historiaBase['test_morisky_valoracio_psicologia'] = $historia->valoracion_psicologia;
                $camposPorCompletar['test_morisky_valoracio_psicologia'] = false;
            }

            if ($camposPorCompletar['adherente'] && !empty($historia->adherente)) {
                $historiaBase['adherente'] = $historia->adherente;
                $camposPorCompletar['adherente'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 COMPLETAR EDUCACIÓN EN SALUD
            // ═══════════════════════════════════════════
            if ($camposPorCompletar['alimentacion'] && !empty($historia->alimentacion)) {
                $historiaBase['alimentacion'] = $historia->alimentacion;
                $camposPorCompletar['alimentacion'] = false;
            }

            if ($camposPorCompletar['disminucion_consumo_sal_azucar'] && !empty($historia->disminucion_consumo_sal_azucar)) {
                $historiaBase['disminucion_consumo_sal_azucar'] = $historia->disminucion_consumo_sal_azucar;
                $camposPorCompletar['disminucion_consumo_sal_azucar'] = false;
            }

            if ($camposPorCompletar['fomento_actividad_fisica'] && !empty($historia->fomento_actividad_fisica)) {
                $historiaBase['fomento_actividad_fisica'] = $historia->fomento_actividad_fisica;
                $camposPorCompletar['fomento_actividad_fisica'] = false;
            }

            if ($camposPorCompletar['importancia_adherencia_tratamiento'] && !empty($historia->importancia_adherencia_tratamiento)) {
                $historiaBase['importancia_adherencia_tratamiento'] = $historia->importancia_adherencia_tratamiento;
                $camposPorCompletar['importancia_adherencia_tratamiento'] = false;
            }

            if ($camposPorCompletar['consumo_frutas_verduras'] && !empty($historia->consumo_frutas_verduras)) {
                $historiaBase['consumo_frutas_verduras'] = $historia->consumo_frutas_verduras;
                $camposPorCompletar['consumo_frutas_verduras'] = false;
            }

            if ($camposPorCompletar['manejo_estres'] && !empty($historia->manejo_estres)) {
                $historiaBase['manejo_estres'] = $historia->manejo_estres;
                $camposPorCompletar['manejo_estres'] = false;
            }

            if ($camposPorCompletar['disminucion_consumo_cigarrillo'] && !empty($historia->disminucion_consumo_cigarrillo)) {
                $historiaBase['disminucion_consumo_cigarrillo'] = $historia->disminucion_consumo_cigarrillo;
                $camposPorCompletar['disminucion_consumo_cigarrillo'] = false;
            }

            if ($camposPorCompletar['disminucion_peso'] && !empty($historia->disminucion_peso)) {
                $historiaBase['disminucion_peso'] = $historia->disminucion_peso;
                $camposPorCompletar['disminucion_peso'] = false;
            }

            // ═══════════════════════════════════════════
            // 🔹 VERIFICAR SI YA COMPLETAMOS TODO
            // ═══════════════════════════════════════════
            if (!in_array(true, $camposPorCompletar)) {
                Log::info('✅ Todos los campos completados, deteniendo búsqueda');
                break;
            }
        }

        Log::info('📊 Resultado final de completar datos DE TODAS LAS ESPECIALIDADES', [
            'medicamentos_final' => count($historiaBase['medicamentos'] ?? []),
            'remisiones_final' => count($historiaBase['remisiones'] ?? []),
            'cups_final' => count($historiaBase['cups'] ?? []),
            'tiene_clasificacion' => !empty($historiaBase['clasificacion_estado_metabolico']),
            'tiene_talla' => !empty($historiaBase['talla']),
            'campos_completados' => count(array_filter($camposPorCompletar)) === 0
        ]);

        return $historiaBase;

    } catch (\Exception $e) {
        Log::error('❌ Error completando datos faltantes', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return $historiaBase;
    }
}
/**
 * ✅ VERIFICAR SI ES LA PRIMERA CONSULTA DE LA ESPECIALIDAD (VERSIÓN CORREGIDA)
 */
private function esPrimeraConsultaDeEspecialidad(string $pacienteUuid, string $especialidad, ?int $citaActualId = null): bool
{
    try {
        Log::info('🔍 Verificando si es PRIMERA CONSULTA de la especialidad', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'cita_actual_id' => $citaActualId
        ]);

        $paciente = \App\Models\Paciente::where('uuid', $pacienteUuid)->first();
        
        if (!$paciente) {
            Log::warning('⚠️ Paciente no encontrado, asumiendo PRIMERA VEZ');
            return true;
        }

        $especialidadNormalizada = strtoupper(str_replace(' ', '', trim($especialidad)));

        // ✅ OBTENER TODAS LAS HISTORIAS **EXCLUYENDO LA CITA ACTUAL**
        $historias = \App\Models\HistoriaClinica::whereHas('cita', function($query) use ($paciente, $citaActualId) {
            $query->where('paciente_uuid', $paciente->uuid)
                  ->whereIn('estado', ['ATENDIDA', 'CONFIRMADA']);
            
            // ✅ EXCLUIR LA CITA ACTUAL
            if ($citaActualId) {
                $query->where('id', '!=', $citaActualId);
            }
        })
        ->with([
            'cita.agenda.usuarioMedico',
            'cita.agenda.especialidad'
        ])
        ->get();

        Log::info('📋 Total de historias encontradas (excluyendo cita actual)', [
            'total' => $historias->count(),
            'paciente_uuid' => $pacienteUuid,
            'cita_actual_excluida' => $citaActualId
        ]);

        if ($historias->isEmpty()) {
            Log::info('✅ No hay historias previas → PRIMERA VEZ');
            return true;
        }

        // ✅ FILTRAR POR ESPECIALIDAD
        $historiasDeEspecialidad = $historias->filter(function($historia) use ($especialidadNormalizada, $especialidad) {
            $cita = $historia->cita;
            $agenda = $cita->agenda ?? null;
            
            if (!$agenda || !$agenda->especialidad) {
                return false;
            }
            
            $especialidadHistoria = $agenda->especialidad->nombre ?? '';
            $especialidadHistoriaNormalizada = strtoupper(str_replace(' ', '', trim($especialidadHistoria)));
            
            $coincide = $especialidadHistoriaNormalizada === $especialidadNormalizada;
            
            Log::info('🔍 Comparando especialidades', [
                'historia_uuid' => $historia->uuid,
                'cita_id' => $historia->cita_id,
                'especialidad_historia' => $especialidadHistoria,
                'especialidad_buscada' => $especialidad,
                'coincide' => $coincide ? '✅ SÍ' : '❌ NO'
            ]);
            
            return $coincide;
        });

        $totalHistorias = $historiasDeEspecialidad->count();
        $esPrimeraVez = $totalHistorias === 0;

        Log::info('✅ Resultado: Verificación de primera consulta', [
            'paciente_uuid' => $pacienteUuid,
            'especialidad' => $especialidad,
            'total_historias_de_especialidad' => $totalHistorias,
            'es_primera_vez' => $esPrimeraVez,
            'tipo_consulta' => $esPrimeraVez ? '🆕 PRIMERA VEZ' : '🔄 CONTROL'
        ]);

        return $esPrimeraVez;

    } catch (\Exception $e) {
        Log::error('❌ Error verificando primera consulta', [
            'error' => $e->getMessage()
        ]);
        return true;
    }
}



/**
 * ✅ DETERMINAR VISTA SEGÚN ESPECIALIDAD - VERSIÓN CORREGIDA
 */
private function determinarVistaSegunEspecialidad(string $especialidad, string $tipoConsulta): array
{
    // ✅ ESPECIALIDADES QUE SOLO TIENEN CONTROL (SIN PRIMERA VEZ)
    $especialidadesSoloControl = ['NEFROLOGIA', 'INTERNISTA'];
    
    // ✅ SI ES UNA ESPECIALIDAD SOLO-CONTROL, FORZAR TIPO CONTROL
    if (in_array($especialidad, $especialidadesSoloControl)) {
        $tipoConsulta = 'CONTROL';
        
        Log::info('🔒 Especialidad solo-control detectada', [
            'especialidad' => $especialidad,
            'tipo_consulta_forzado' => 'CONTROL'
        ]);
    }
    
    $especialidadesConComplementaria = [
        'REFORMULACION', 'NUTRICIONISTA', 'PSICOLOGIA', 'NEFROLOGIA', 
        'INTERNISTA', 'FISIOTERAPIA', 'TRABAJO SOCIAL'
    ];

    $usaComplementaria = in_array($especialidad, $especialidadesConComplementaria);

    // ✅ MAPEO DE VISTAS - CORREGIDO
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
            // ✅ SOLO CONTROL - AMBOS APUNTAN A LA MISMA VISTA
            'PRIMERA VEZ' => 'nefrologia.control',
            'CONTROL' => 'nefrologia.control'
        ],
        'INTERNISTA' => [
            // ✅ SOLO CONTROL - AMBOS APUNTAN A LA MISMA VISTA
            'PRIMERA VEZ' => 'internista.control',
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
        'tipo_consulta' => $tipoConsulta, // ✅ Retorna el tipo forzado
        'solo_control' => in_array($especialidad, $especialidadesSoloControl) // ✅ NUEVO FLAG
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
 * ✅ PROCESAR HISTORIA PARA FRONTEND - VERSIÓN ULTRA PROTEGIDA
 */
private function procesarHistoriaParaFrontend(\App\Models\HistoriaClinica $historia): array
{
    try {
        Log::info('🔧 Procesando historia para frontend', [
            'historia_uuid' => $historia->uuid,
            'historia_id' => $historia->id
        ]);

        // ✅ CARGAR RELACIONES SI NO ESTÁN CARGADAS (PROTECCIÓN DOBLE)
        if (!$historia->relationLoaded('historiaMedicamentos')) {
            $historia->load('historiaMedicamentos.medicamento');
        }
        if (!$historia->relationLoaded('historiaDiagnosticos')) {
            $historia->load('historiaDiagnosticos.diagnostico');
        }
        if (!$historia->relationLoaded('historiaRemisiones')) {
            $historia->load('historiaRemisiones.remision');
        }
        if (!$historia->relationLoaded('historiaCups')) {
            $historia->load('historiaCups.cups');
        }

        // ✅ PROCESAR MEDICAMENTOS CON PROTECCIÓN TRIPLE
        $medicamentos = [];
        if ($historia->historiaMedicamentos && $historia->historiaMedicamentos->isNotEmpty()) {
            foreach ($historia->historiaMedicamentos as $item) {
                try {
                    // ✅ VERIFICAR QUE EXISTE LA RELACIÓN
                    if (!$item->medicamento) {
                        Log::warning('⚠️ Medicamento sin relación', [
                            'historia_medicamento_id' => $item->id
                        ]);
                        continue;
                    }

                    $medicamentos[] = [
                        'uuid' => $item->uuid ?? null,
                        'medicamento_id' => $item->medicamento->uuid ?? $item->medicamento->id,
                        'cantidad' => $item->cantidad ?? '1',
                        'dosis' => $item->dosis ?? 'Según indicación',
                        'medicamento' => [
                            'uuid' => $item->medicamento->uuid ?? $item->medicamento->id,
                            'id' => $item->medicamento->id,
                            'nombre' => $item->medicamento->nombre ?? 'Sin nombre',
                            'principio_activo' => $item->medicamento->principio_activo ?? ''
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('❌ Error procesando medicamento individual', [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id ?? 'N/A'
                    ]);
                }
            }
        }

        // ✅ PROCESAR DIAGNÓSTICOS CON PROTECCIÓN TRIPLE
        $diagnosticos = [];
        if ($historia->historiaDiagnosticos && $historia->historiaDiagnosticos->isNotEmpty()) {
            foreach ($historia->historiaDiagnosticos as $item) {
                try {
                    // ✅ VERIFICAR QUE EXISTE LA RELACIÓN
                    if (!$item->diagnostico) {
                        Log::warning('⚠️ Diagnóstico sin relación', [
                            'historia_diagnostico_id' => $item->id
                        ]);
                        continue;
                    }

                    $diagnosticos[] = [
                        'uuid' => $item->uuid ?? null,
                        'diagnostico_id' => $item->diagnostico->uuid ?? $item->diagnostico->id,
                        'tipo' => $item->tipo ?? 'PRINCIPAL',
                        'tipo_diagnostico' => $item->tipo_diagnostico ?? 'IMPRESION_DIAGNOSTICA',
                        'diagnostico' => [
                            'uuid' => $item->diagnostico->uuid ?? $item->diagnostico->id,
                            'id' => $item->diagnostico->id,
                            'codigo' => $item->diagnostico->codigo ?? 'Sin código',
                            'nombre' => $item->diagnostico->nombre ?? 'Sin nombre'
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('❌ Error procesando diagnóstico individual', [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id ?? 'N/A'
                    ]);
                }
            }
        }

        // ✅ PROCESAR REMISIONES CON PROTECCIÓN TRIPLE
        $remisiones = [];
        if ($historia->historiaRemisiones && $historia->historiaRemisiones->isNotEmpty()) {
            foreach ($historia->historiaRemisiones as $item) {
                try {
                    // ✅ VERIFICAR QUE EXISTE LA RELACIÓN
                    if (!$item->remision) {
                        Log::warning('⚠️ Remisión sin relación', [
                            'historia_remision_id' => $item->id
                        ]);
                        continue;
                    }

                    $remisiones[] = [
                        'uuid' => $item->uuid ?? null,
                        'remision_id' => $item->remision->uuid ?? $item->remision->id,
                        'observacion' => $item->observacion ?? '',
                        'remision' => [
                            'uuid' => $item->remision->uuid ?? $item->remision->id,
                            'id' => $item->remision->id,
                            'nombre' => $item->remision->nombre ?? 'Sin nombre',
                            'tipo' => $item->remision->tipo ?? ''
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('❌ Error procesando remisión individual', [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id ?? 'N/A'
                    ]);
                }
            }
        }

        // ✅ PROCESAR CUPS CON PROTECCIÓN TRIPLE
        $cups = [];
        if ($historia->historiaCups && $historia->historiaCups->isNotEmpty()) {
            foreach ($historia->historiaCups as $item) {
                try {
                    // ✅ VERIFICAR QUE EXISTE LA RELACIÓN
                    if (!$item->cups) {
                        Log::warning('⚠️ CUPS sin relación', [
                            'historia_cups_id' => $item->id
                        ]);
                        continue;
                    }

                    $cups[] = [
                        'uuid' => $item->uuid ?? null,
                        'cups_id' => $item->cups->uuid ?? $item->cups->id,
                        'observacion' => $item->observacion ?? '',
                        'cups' => [
                            'uuid' => $item->cups->uuid ?? $item->cups->id,
                            'id' => $item->cups->id,
                            'codigo' => $item->cups->codigo ?? 'Sin código',
                            'nombre' => $item->cups->nombre ?? 'Sin nombre'
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('❌ Error procesando CUPS individual', [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id ?? 'N/A'
                    ]);
                }
            }
        }

        // ✅ LOG DE RESULTADO FINAL
        Log::info('✅ Historia procesada exitosamente', [
            'historia_uuid' => $historia->uuid,
            'medicamentos_count' => count($medicamentos),
            'diagnosticos_count' => count($diagnosticos),
            'remisiones_count' => count($remisiones),
            'cups_count' => count($cups)
        ]);

        // ✅ RETORNAR ESTRUCTURA COMPLETA CON VALORES SEGUROS
        return [
            // ✅ ARRAYS PROCESADOS (SIEMPRE ARRAYS, NUNCA NULL)
            'medicamentos' => $medicamentos,
            'remisiones' => $remisiones,
            'diagnosticos' => $diagnosticos,
            'cups' => $cups,

            // ✅ CLASIFICACIONES (CON ?? NULL PARA SEGURIDAD)
            'clasificacion_estado_metabolico' => $historia->clasificacion_estado_metabolico ?? null,
            'clasificacion_hta' => $historia->clasificacion_hta ?? null,
            'clasificacion_dm' => $historia->clasificacion_dm ?? null,
            'clasificacion_rcv' => $historia->clasificacion_rcv ?? null,
            'clasificacion_erc_estado' => $historia->clasificacion_erc_estado ?? null,
            'clasificacion_erc_categoria_ambulatoria_persistente' => $historia->clasificacion_erc_categoria_ambulatoria_persistente ?? null,

            // ✅ TASAS DE FILTRACIÓN
            'tasa_filtracion_glomerular_ckd_epi' => $historia->tasa_filtracion_glomerular_ckd_epi ?? null,
            'tasa_filtracion_glomerular_gockcroft_gault' => $historia->tasa_filtracion_glomerular_gockcroft_gault ?? null,

            // ✅ ANTECEDENTES PERSONALES
            'hipertension_arterial_personal' => $historia->hipertension_arterial_personal ?? 'NO',
            'obs_hipertension_arterial_personal' => $historia->obs_personal_hipertension_arterial ?? null,
            'diabetes_mellitus_personal' => $historia->diabetes_mellitus_personal ?? 'NO',
            'obs_diabetes_mellitus_personal' => $historia->obs_personal_mellitus ?? null,

            // ✅ TALLA
            'talla' => $historia->talla ?? null,

            // ✅ TEST DE MORISKY
            'test_morisky_olvida_tomar_medicamentos' => $historia->olvida_tomar_medicamentos ?? null,
            'test_morisky_toma_medicamentos_hora_indicada' => $historia->toma_medicamentos_hora_indicada ?? null,
            'test_morisky_cuando_esta_bien_deja_tomar_medicamentos' => $historia->cuando_esta_bien_deja_tomar_medicamentos ?? null,
            'test_morisky_siente_mal_deja_tomarlos' => $historia->siente_mal_deja_tomarlos ?? null,
            'test_morisky_valoracio_psicologia' => $historia->valoracion_psicologia ?? null,
            'adherente' => $historia->adherente ?? null,

            // ✅ EDUCACIÓN EN SALUD
            'alimentacion' => $historia->alimentacion ?? null,
            'disminucion_consumo_sal_azucar' => $historia->disminucion_consumo_sal_azucar ?? null,
            'fomento_actividad_fisica' => $historia->fomento_actividad_fisica ?? null,
            'importancia_adherencia_tratamiento' => $historia->importancia_adherencia_tratamiento ?? null,
            'consumo_frutas_verduras' => $historia->consumo_frutas_verduras ?? null,
            'manejo_estres' => $historia->manejo_estres ?? null,
            'disminucion_consumo_cigarrillo' => $historia->disminucion_consumo_cigarrillo ?? null,
            'disminucion_peso' => $historia->disminucion_peso ?? null,

            // ✅ METADATOS
            'historia_uuid' => $historia->uuid,
            'historia_id' => $historia->id,
            'created_at' => $historia->created_at ? $historia->created_at->toIso8601String() : null,
        ];

    } catch (\Exception $e) {
        Log::error('❌ Error procesando historia para frontend', [
            'error' => $e->getMessage(),
            'historia_id' => $historia->id ?? 'N/A',
            'historia_uuid' => $historia->uuid ?? 'N/A',
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'trace' => $e->getTraceAsString()
        ]);
        
        // ✅ RETORNAR ESTRUCTURA VACÍA PERO VÁLIDA
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
