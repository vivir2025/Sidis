<?php
// app/Models/HistoriaClinicaComplementaria.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HistoriaClinicaComplementaria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historias_clinicas_complementarias';

    protected $fillable = [
        'uuid',
        'historia_clinica_id',
        
        // Antecedentes patológicos
        'sistema_nervioso_nefro_inter',
        'sistema_hemolinfatico',
        'aparato_digestivo',
        'organo_sentido',
        'endocrino_metabolico',
        'inmunologico',
        'cancer_tumores_radioterapia_quimio',
        'glandula_mamaria',
        'hipertension_diabetes_erc',
        'reacciones_alergica',
        'cardio_vasculares',
        'respiratorios',
        'urinarias',
        'osteoarticulares',
        'infecciosos',
        'cirugia_trauma',
        'tratamiento_medicacion',
        'antecedente_quirurgico',
        'antecedentes_familiares',
        'consumo_tabaco',
        'antecedentes_alcohol',
        'sedentarismo',
        'ginecologico',
        'citologia_vaginal',
        'menarquia',
        'gestaciones',
        'parto',
        'aborto',
        'cesaria',
        'metodo_conceptivo',
        'metodo_conceptivo_cual',
        'antecedente_personal',
        
        // Descripciones detalladas
        'descripcion_sistema_nervioso',
        'descripcion_sistema_hemolinfatico',
        'descripcion_aparato_digestivo',
        'descripcion_organos_sentidos',
        'descripcion_endocrino_metabolico',
        'descripcion_inmunologico',
        'descripcion_cancer_tumores_radio_quimioterapia',
        'descripcion_glandulas_mamarias',
        'descripcion_hipertension_diabetes_erc',
        'descripcion_reacion_alergica',
        'descripcion_cardio_vasculares',
        'descripcion_respiratorios',
        'descripcion_urinarias',
        'descripcion_osteoarticulares',
        'descripcion_infecciosos',
        'descripcion_cirugias_traumas',
        'descripcion_tratamiento_medicacion',
        'descripcion_antecedentes_quirurgicos',
        'descripcion_antecedentes_familiares',
        'descripcion_consumo_tabaco',
        'descripcion_antecedentes_alcohol',
        'descripcion_sedentarismo',
        'descripcion_ginecologicos',
        'descripcion_citologia_vaginal',
        
        // Neurológico y estado mental
        'neurologico_estado_mental',
        'obs_neurologico_estado_mental',
        
        // Estructura familiar
        'estructura_familiar',
        'cantidad_habitantes',
        'cantidad_conforman_familia',
        'composicion_familiar',
        
        // Vivienda
        'tipo_vivienda',
        'tenencia_vivienda',
        'material_paredes',
        'material_pisos',
        'espacios_sala',
        'comedor',
        'banio',
        'cocina',
        'patio',
        'cantidad_dormitorios',
        'cantidad_personas_ocupan_cuarto',
        
        // Servicios públicos
        'energia_electrica',
        'alcantarillado',
        'gas_natural',
        'centro_atencion',
        'acueducto',
        'centro_culturales',
        'ventilacion',
        'organizacion',
        'centro_educacion',
        'centro_recreacion_esparcimiento',
        
        // Evaluación psicosocial
        'dinamica_familiar',
        'diagnostico',
        'acciones_seguir',
        'motivo_consulta',
        'psicologia_descripcion_problema',
        'psicologia_red_apoyo',
        'psicologia_plan_intervencion_recomendacion',
        'psicologia_tratamiento_actual_adherencia',
        'analisis_conclusiones',
        'psicologia_comportamiento_consulta',
        
        // Seguimiento
        'objetivo_visita',
        'situacion_encontrada',
        'compromiso',
        'recomendaciones',
        'siguiente_seguimiento',
        'enfermedad_diagnostica',
        
        // Antecedentes adicionales
        'habito_intestinal',
        'quirurgicos',
        'quirurgicos_observaciones',
        'alergicos',
        'alergicos_observaciones',
        'familiares',
        'familiares_observaciones',
        'psa',
        'psa_observaciones',
        'farmacologicos',
        'farmacologicos_observaciones',
        'sueno',
        'sueno_observaciones',
        'tabaquismo_observaciones',
        'ejercicio',
        'ejercicio_observaciones',
        
        // Gineco-obstétricos
        'embarazo_actual',
        'semanas_gestacion',
        'climatero',
        
        // Evaluación nutricional
        'tolerancia_via_oral',
        'percepcion_apetito',
        'percepcion_apetito_observacion',
        'alimentos_preferidos',
        'alimentos_rechazados',
        'suplemento_nutricionales',
        'dieta_especial',
        'dieta_especial_cual',
        
        // Horarios de comida
        'desayuno_hora',
        'desayuno_hora_observacion',
        'media_manana_hora',
        'media_manana_hora_observacion',
        'almuerzo_hora',
        'almuerzo_hora_observacion',
        'media_tarde_hora',
        'media_tarde_hora_observacion',
        'cena_hora',
        'cena_hora_observacion',
        'refrigerio_nocturno_hora',
        'refrigerio_nocturno_hora_observacion',
        
        // Evaluación nutricional detallada
        'peso_ideal',
        'interpretacion',
        'meta_meses',
        'analisis_nutricional',
        'plan_seguir',
        'avance_paciente',
        
        // Frecuencia de consumo
        'comida_desayuno',
        'comida_almuerzo',
        'comida_medio_almuerzo',
        'comida_cena',
        'comida_medio_desayuno',
        
        // Grupos de alimentos
        'lacteo',
        'lacteo_observacion',
        'huevo',
        'huevo_observacion',
        'embutido',
        'embutido_observacion',
        'carne_roja',
        'carne_blanca',
        'carne_vicera',
        'carne_observacion',
        'leguminosas',
        'leguminosas_observacion',
        'frutas_jugo',
        'frutas_porcion',
        'frutas_observacion',
        'verduras_hortalizas',
        'vh_observacion',
        'cereales',
        'cereales_observacion',
        'rtp',
        'rtp_observacion',
        'azucar_dulce',
        'ad_observacion',
        
        // Diagnóstico nutricional
        'diagnostico_nutri',
        'plan_seguir_nutri',
        
        // Evaluación física y terapéutica
        'actitud',
        'evaluacion_d',
        'evaluacion_p',
        'estado',
        'evaluacion_dolor',
        'evaluacion_os',
        'evaluacion_neu',
        'comitante'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($complementaria) {
            if (empty($complementaria->uuid)) {
                $complementaria->uuid = Str::uuid();
            }
        });
    }

    // ================================
    // RELACIONES
    // ================================
    
    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    // ================================
    // SCOPES
    // ================================
    
    public function scopePorHistoria($query, $historiaId)
    {
        return $query->where('historia_clinica_id', $historiaId);
    }

    // ================================
    // ACCESSORS - AGRUPADORES DE INFORMACIÓN
    // ================================
    
    public function getAntecedentesPatologicosAttribute()
    {
        return [
            'sistema_nervioso_nefro_inter' => $this->sistema_nervioso_nefro_inter,
            'sistema_hemolinfatico' => $this->sistema_hemolinfatico,
            'aparato_digestivo' => $this->aparato_digestivo,
            'organo_sentido' => $this->organo_sentido,
            'endocrino_metabolico' => $this->endocrino_metabolico,
            'inmunologico' => $this->inmunologico,
            'cancer_tumores_radioterapia_quimio' => $this->cancer_tumores_radioterapia_quimio,
            'glandula_mamaria' => $this->glandula_mamaria,
            'hipertension_diabetes_erc' => $this->hipertension_diabetes_erc,
            'reacciones_alergica' => $this->reacciones_alergica,
            'cardio_vasculares' => $this->cardio_vasculares,
            'respiratorios' => $this->respiratorios,
            'urinarias' => $this->urinarias,
            'osteoarticulares' => $this->osteoarticulares,
            'infecciosos' => $this->infecciosos,
            'cirugia_trauma' => $this->cirugia_trauma,
            'tratamiento_medicacion' => $this->tratamiento_medicacion,
        ];
    }

    public function getEstructuraFamiliarDataAttribute()
    {
        return [
            'estructura_familiar' => $this->estructura_familiar,
            'cantidad_habitantes' => $this->cantidad_habitantes,
            'cantidad_conforman_familia' => $this->cantidad_conforman_familia,
            'composicion_familiar' => $this->composicion_familiar,
        ];
    }

    public function getViviendaDataAttribute()
    {
        return [
            'tipo_vivienda' => $this->tipo_vivienda,
            'tenencia_vivienda' => $this->tenencia_vivienda,
            'material_paredes' => $this->material_paredes,
            'material_pisos' => $this->material_pisos,
            'espacios_sala' => $this->espacios_sala,
            'comedor' => $this->comedor,
            'banio' => $this->banio,
            'cocina' => $this->cocina,
            'patio' => $this->patio,
            'cantidad_dormitorios' => $this->cantidad_dormitorios,
            'cantidad_personas_ocupan_cuarto' => $this->cantidad_personas_ocupan_cuarto,
        ];
    }

    public function getServiciosPublicosAttribute()
    {
        return [
            'energia_electrica' => $this->energia_electrica,
            'alcantarillado' => $this->alcantarillado,
            'gas_natural' => $this->gas_natural,
            'centro_atencion' => $this->centro_atencion,
            'acueducto' => $this->acueducto,
            'centro_culturales' => $this->centro_culturales,
            'ventilacion' => $this->ventilacion,
            'organizacion' => $this->organizacion,
            'centro_educacion' => $this->centro_educacion,
            'centro_recreacion_esparcimiento' => $this->centro_recreacion_esparcimiento,
        ];
    }

    public function getEvaluacionPsicosocialAttribute()
    {
        return [
            'dinamica_familiar' => $this->dinamica_familiar,
            'diagnostico' => $this->diagnostico,
            'acciones_seguir' => $this->acciones_seguir,
            'motivo_consulta' => $this->motivo_consulta,
            'psicologia_descripcion_problema' => $this->psicologia_descripcion_problema,
            'psicologia_red_apoyo' => $this->psicologia_red_apoyo,
            'psicologia_plan_intervencion_recomendacion' => $this->psicologia_plan_intervencion_recomendacion,
            'psicologia_tratamiento_actual_adherencia' => $this->psicologia_tratamiento_actual_adherencia,
            'analisis_conclusiones' => $this->analisis_conclusiones,
            'psicologia_comportamiento_consulta' => $this->psicologia_comportamiento_consulta,
        ];
    }

    public function getEvaluacionNutricionalAttribute()
    {
        return [
            'tolerancia_via_oral' => $this->tolerancia_via_oral,
            'percepcion_apetito' => $this->percepcion_apetito,
            'percepcion_apetito_observacion' => $this->percepcion_apetito_observacion,
            'alimentos_preferidos' => $this->alimentos_preferidos,
            'alimentos_rechazados' => $this->alimentos_rechazados,
            'suplemento_nutricionales' => $this->suplemento_nutricionales,
            'dieta_especial' => $this->dieta_especial,
            'dieta_especial_cual' => $this->dieta_especial_cual,
            'peso_ideal' => $this->peso_ideal,
            'interpretacion' => $this->interpretacion,
            'meta_meses' => $this->meta_meses,
            'analisis_nutricional' => $this->analisis_nutricional,
            'plan_seguir' => $this->plan_seguir,
            'avance_paciente' => $this->avance_paciente,
        ];
    }

    public function getHorariosComidaAttribute()
    {
        return [
            'desayuno' => [
                'hora' => $this->desayuno_hora,
                'observacion' => $this->desayuno_hora_observacion
            ],
            'media_manana' => [
                'hora' => $this->media_manana_hora,
                'observacion' => $this->media_manana_hora_observacion
            ],
            'almuerzo' => [
                'hora' => $this->almuerzo_hora,
                'observacion' => $this->almuerzo_hora_observacion
            ],
            'media_tarde' => [
                'hora' => $this->media_tarde_hora,
                'observacion' => $this->media_tarde_hora_observacion
            ],
            'cena' => [
                'hora' => $this->cena_hora,
                'observacion' => $this->cena_hora_observacion
            ],
            'refrigerio_nocturno' => [
                'hora' => $this->refrigerio_nocturno_hora,
                'observacion' => $this->refrigerio_nocturno_hora_observacion
            ],
        ];
    }

    public function getGruposAlimentosAttribute()
    {
        return [
            'lacteo' => [
                'consumo' => $this->lacteo,
                'observacion' => $this->lacteo_observacion
            ],
            'huevo' => [
                'consumo' => $this->huevo,
                'observacion' => $this->huevo_observacion
            ],
            'embutido' => [
                'consumo' => $this->embutido,
                'observacion' => $this->embutido_observacion
            ],
            'carnes' => [
                'roja' => $this->carne_roja,
                'blanca' => $this->carne_blanca,
                'vicera' => $this->carne_vicera,
                'observacion' => $this->carne_observacion
            ],
            'leguminosas' => [
                'consumo' => $this->leguminosas,
                'observacion' => $this->leguminosas_observacion
            ],
            'frutas' => [
                'jugo' => $this->frutas_jugo,
                'porcion' => $this->frutas_porcion,
                'observacion' => $this->frutas_observacion
            ],
            'verduras_hortalizas' => [
                'consumo' => $this->verduras_hortalizas,
                'observacion' => $this->vh_observacion
            ],
            'cereales' => [
                'consumo' => $this->cereales,
                'observacion' => $this->cereales_observacion
            ],
            'rtp' => [
                'consumo' => $this->rtp,
                'observacion' => $this->rtp_observacion
            ],
            'azucar_dulce' => [
                'consumo' => $this->azucar_dulce,
                'observacion' => $this->ad_observacion
            ],
        ];
    }

    public function getAntecedentesGinecoObstetricosAttribute()
    {
        return [
            'ginecologico' => $this->ginecologico,
            'citologia_vaginal' => $this->citologia_vaginal,
            'menarquia' => $this->menarquia,
            'gestaciones' => $this->gestaciones,
            'parto' => $this->parto,
            'aborto' => $this->aborto,
            'cesaria' => $this->cesaria,
            'metodo_conceptivo' => $this->metodo_conceptivo,
            'metodo_conceptivo_cual' => $this->metodo_conceptivo_cual,
            'embarazo_actual' => $this->embarazo_actual,
            'semanas_gestacion' => $this->semanas_gestacion,
            'climatero' => $this->climatero,
        ];
    }

    // ================================
    // MÉTODOS AUXILIARES
    // ================================
    
    public function tieneAntecedentesPatologicos()
    {
        $campos = [
            'sistema_nervioso_nefro_inter', 'sistema_hemolinfatico', 'aparato_digestivo',
            'organo_sentido', 'endocrino_metabolico', 'inmunologico', 'cancer_tumores_radioterapia_quimio',
            'glandula_mamaria', 'hipertension_diabetes_erc', 'reacciones_alergica', 'cardio_vasculares',
            'respiratorios', 'urinarias', 'osteoarticulares', 'infecciosos', 'cirugia_trauma'
        ];

        foreach ($campos as $campo) {
            if (!empty($this->$campo)) {
                return true;
            }
        }

        return false;
    }

    public function tieneEvaluacionNutricional()
    {
        return !empty($this->tolerancia_via_oral) || 
               !empty($this->percepcion_apetito) || 
               !empty($this->peso_ideal) || 
               !empty($this->analisis_nutricional);
    }

    public function tieneEvaluacionPsicosocial()
    {
        return !empty($this->dinamica_familiar) || 
               !empty($this->psicologia_descripcion_problema) || 
               !empty($this->analisis_conclusiones);
    }
}
