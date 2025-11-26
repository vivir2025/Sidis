<?php
// app/Models/HistoriaClinica.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Traits\{HasUuidTrait, SyncableTrait};

class HistoriaClinica extends Model
{
    use SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'uuid', 'sede_id', 'cita_id', 'finalidad', 'acompanante', 'acu_telefono',
        'acu_parentesco', 'causa_externa', 'motivo_consulta', 'enfermedad_actual',
        'discapacidad_fisica', 'discapacidad_visual', 'discapacidad_mental',
        'discapacidad_auditiva', 'discapacidad_intelectual', 'drogo_dependiente',
        'drogo_dependiente_cual', 'peso', 'talla', 'imc', 'clasificacion',
        'tasa_filtracion_glomerular_ckd_epi', 'tasa_filtracion_glomerular_gockcroft_gault',
        
        // Antecedentes familiares
        'hipertension_arterial', 'parentesco_hipertension', 'diabetes_mellitus', 
        'parentesco_mellitus', 'artritis', 'parentesco_artritis',
        'enfermedad_cardiovascular', 'parentesco_cardiovascular',
        'antecedente_metabolico', 'parentesco_metabolico',
        'cancer_mama_estomago_prostata_colon', 'parentesco_cancer',
        'leucemia', 'parentesco_leucemia', 'vih', 'parentesco_vih',
        'otro', 'parentesco_otro',
        
        // Antecedentes personales
        'hipertension_arterial_personal', 'obs_personal_hipertension_arterial',
        'diabetes_mellitus_personal', 'obs_personal_mellitus',
        'enfermedad_cardiovascular_personal', 'obs_personal_enfermedad_cardiovascular',
        'arterial_periferica_personal', 'obs_personal_arterial_periferica',
        'carotidea_personal', 'obs_personal_carotidea',
        'aneurisma_aorta_personal', 'obs_personal_aneurisma_aorta',
        'sindrome_coronario_agudo_angina_personal', 'obs_personal_sindrome_coronario',
        'artritis_personal', 'obs_personal_artritis',
        'iam_personal', 'obs_personal_iam',
        'revascul_coronaria_personal', 'obs_personal_revascul_coronaria',
        'insuficiencia_cardiaca_personal', 'obs_personal_insuficiencia_cardiaca',
        'amputacion_pie_diabetico_personal', 'obs_personal_amputacion_pie_diabetico',
        'enfermedad_pulmonar_personal', 'obs_personal_enfermedad_pulmonar',
        'victima_maltrato_personal', 'obs_personal_maltrato_personal',
        'antecedentes_quirurgicos', 'obs_personal_antecedentes_quirurgicos',
        'acontosis_personal', 'obs_personal_acontosis',
        'otro_personal', 'obs_personal_otro', 'insulina_requiriente',
        
        // Test Morisky
        'olvida_tomar_medicamentos', 'toma_medicamentos_hora_indicada',
        'cuando_esta_bien_deja_tomar_medicamentos', 'siente_mal_deja_tomarlos',
        'valoracion_psicologia',
        
        // Revisión por sistemas
        'cabeza', 'orl', 'cardiovascular', 'gastrointestinal', 'osteoatromuscular',
        'snc', 'revision_sistemas',
        
        // Signos vitales
        'presion_arterial_sistolica_sentado_pie', 'presion_arterial_distolica_sentado_pie',
        'presion_arterial_sistolica_acostado', 'presion_arterial_distolica_acostado',
        'frecuencia_cardiaca', 'frecuencia_respiratoria',
        
        // Examen físico
        'ef_cabeza', 'obs_cabeza', 'agudeza_visual', 'obs_agudeza_visual',
        'fundoscopia', 'obs_fundoscopia', 'cuello', 'obs_cuello',
        'torax', 'obs_torax', 'mamas', 'obs_mamas', 'abdomen', 'obs_abdomen',
        'genito_urinario', 'obs_genito_urinario', 'extremidades', 'obs_extremidades',
        'piel_anexos_pulsos', 'obs_piel_anexos_pulsos', 'sistema_nervioso', 'obs_sistema_nervioso',
        'capacidad_cognitiva', 'obs_capacidad_cognitiva', 'orientacion', 'obs_orientacion',
        'reflejo_aquiliar', 'obs_reflejo_aquiliar', 'reflejo_patelar', 'obs_reflejo_patelar',
        'hallazgo_positivo_examen_fisico',
        
        // Factores de riesgo
        'tabaquismo', 'obs_tabaquismo', 'dislipidemia', 'obs_dislipidemia',
        'menor_cierta_edad', 'obs_menor_cierta_edad', 'perimetro_abdominal', 'obs_perimetro_abdominal',
        'condicion_clinica_asociada', 'obs_condicion_clinica_asociada',
        'lesion_organo_blanco', 'descripcion_lesion_organo_blanco', 'obs_lesion_organo_blanco',
        
        // Clasificaciones
        'clasificacion_hta', 'clasificacion_dm', 'clasificacion_erc_estado',
        'clasificacion_erc_categoria_ambulatoria_persistente', 'clasificacion_rcv',
        
        // Educación
        'alimentacion', 'disminucion_consumo_sal_azucar', 'fomento_actividad_fisica',
        'importancia_adherencia_tratamiento', 'consumo_frutas_verduras', 'manejo_estres',
        'disminucion_consumo_cigarrillo', 'disminucion_peso',
        
        'observaciones_generales',
        
        // Examen físico adicional
        'oidos', 'nariz_senos_paranasales', 'cavidad_oral', 'cardio_respiratorio',
        'musculo_esqueletico', 'inspeccion_sensibilidad_pies', 'capacidad_cognitiva_orientacion',
        
        // Medicina tradicional
        'recibe_tratamiento_alternativo', 'recibe_tratamiento_con_plantas_medicinales',
        'recibe_ritual_medicina_tradicional',
        
        // Alimentación
        'numero_frutas_diarias', 'elevado_consumo_grasa_saturada', 'adiciona_sal_despues_preparar_comida',
        
        'general', 'respiratorio', 'adherente',
        
        // Exámenes complementarios
        'ecografia_renal', 'razon_reformulacion', 'motivo_reformulacion',
        'reformulacion_quien_reclama', 'reformulacion_nombre_reclama',
        'electrocardiograma', 'ecocardiograma', 'adicional',
        
        'clasificacion_estado_metabolico', 'fex_es', 'fex_es1', 'fex_es2'
    ];

    protected $casts = [
        'peso' => 'decimal:2',
        'talla' => 'decimal:2',
        'imc' => 'decimal:2',
        'tasa_filtracion_glomerular_ckd_epi' => 'decimal:2',
        'tasa_filtracion_glomerular_gockcroft_gault' => 'decimal:2',
        'presion_arterial_sistolica_sentado_pie' => 'decimal:2',
        'presion_arterial_distolica_sentado_pie' => 'decimal:2',
        'presion_arterial_sistolica_acostado' => 'decimal:2',
        'presion_arterial_distolica_acostado' => 'decimal:2',
        'frecuencia_cardiaca' => 'decimal:2',
        'frecuencia_respiratoria' => 'decimal:2',
        'numero_frutas_diarias' => 'integer',
    ];

    // ✅ RELACIONES PRINCIPALES
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function paciente()
    {
        return $this->hasOneThrough(
            Paciente::class,
            Cita::class,
            'id', 
            'id', 
            'cita_id', 
            'paciente_id'
        );
    }

      // ✅✅✅ RELACIONES CORREGIDAS CON FOREIGN KEY EXPLÍCITA ✅✅✅
    
    /**
     * ✅ DIAGNÓSTICOS
     * Especifica explícitamente la foreign key
     */
    public function historiaDiagnosticos(): HasMany
    {
        return $this->hasMany(HistoriaDiagnostico::class, 'historia_clinica_id', 'id');
    }

    /**
     * ✅ MEDICAMENTOS
     * Especifica explícitamente la foreign key
     */
    public function historiaMedicamentos(): HasMany
    {
        return $this->hasMany(HistoriaMedicamento::class, 'historia_clinica_id', 'id');
    }

    /**
     * ✅ REMISIONES
     * Especifica explícitamente la foreign key
     */
    public function historiaRemisiones(): HasMany
    {
        return $this->hasMany(HistoriaRemision::class, 'historia_clinica_id', 'id');
    }

    /**
     * ✅ CUPS
     * Especifica explícitamente la foreign key
     */
    public function historiaCups(): HasMany
    {
        return $this->hasMany(HistoriaCups::class, 'historia_clinica_id', 'id');
    }

    public function complementaria(): HasOne
{
    return $this->hasOne(HistoriaClinicaComplementaria::class, 'historia_clinica_id');
}


    // ✅ RELACIONES ADICIONALES
    public function incapacidades(): HasMany
    {
        return $this->hasMany(Incapacidad::class);
    }

    public function examenesPdf(): HasMany
    {
        return $this->hasMany(ExamenPdf::class);
    }

    // Scopes
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }
}
