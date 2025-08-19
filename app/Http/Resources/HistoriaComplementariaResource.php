<?php
// app/Http/Resources/HistoriaComplementariaResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoriaComplementariaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            
            // Antecedentes patológicos
            'antecedentes_patologicos' => [
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
                'tratamiento_medicacion' => $this->tratamiento_medicacion
            ],
            
            // Descripciones detalladas
            'descripciones' => [
                'sistema_nervioso' => $this->descripcion_sistema_nervioso,
                'sistema_hemolinfatico' => $this->descripcion_sistema_hemolinfatico,
                'aparato_digestivo' => $this->descripcion_aparato_digestivo,
                'organos_sentidos' => $this->descripcion_organos_sentidos,
                'endocrino_metabolico' => $this->descripcion_endocrino_metabolico,
                'inmunologico' => $this->descripcion_inmunologico,
                'cancer_tumores' => $this->descripcion_cancer_tumores_radio_quimioterapia,
                'glandulas_mamarias' => $this->descripcion_glandulas_mamarias,
                'hipertension_diabetes_erc' => $this->descripcion_hipertension_diabetes_erc,
                'reacion_alergica' => $this->descripcion_reacion_alergica,
                'cardio_vasculares' => $this->descripcion_cardio_vasculares,
                'respiratorios' => $this->descripcion_respiratorios,
                'urinarias' => $this->descripcion_urinarias,
                'osteoarticulares' => $this->descripcion_osteoarticulares,
                'infecciosos' => $this->descripcion_infecciosos,
                'cirugias_traumas' => $this->descripcion_cirugias_traumas,
                'tratamiento_medicacion' => $this->descripcion_tratamiento_medicacion
            ],
            
            // Estructura familiar
            'estructura_familiar' => [
                'tipo' => $this->estructura_familiar,
                'cantidad_habitantes' => $this->cantidad_habitantes,
                'cantidad_conforman_familia' => $this->cantidad_conforman_familia,
                'composicion_familiar' => $this->composicion_familiar
            ],
            
            // Vivienda
            'vivienda' => [
                'tipo' => $this->tipo_vivienda,
                'tenencia' => $this->tenencia_vivienda,
                'material_paredes' => $this->material_paredes,
                'material_pisos' => $this->material_pisos,
                'espacios' => [
                    'sala' => $this->espacios_sala,
                    'comedor' => $this->comedor,
                    'banio' => $this->banio,
                    'cocina' => $this->cocina,
                    'patio' => $this->patio,
                    'cantidad_dormitorios' => $this->cantidad_dormitorios,
                    'cantidad_personas_ocupan_cuarto' => $this->cantidad_personas_ocupan_cuarto
                ]
            ],
            
            // Servicios públicos
            'servicios_publicos' => [
                'energia_electrica' => $this->energia_electrica,
                'alcantarillado' => $this->alcantarillado,
                'gas_natural' => $this->gas_natural,
                'acueducto' => $this->acueducto,
                'ventilacion' => $this->ventilacion
            ],
            
            // Evaluación psicosocial
            'evaluacion_psicosocial' => [
                'dinamica_familiar' => $this->dinamica_familiar,
                'diagnostico' => $this->diagnostico,
                'acciones_seguir' => $this->acciones_seguir,
                'motivo_consulta' => $this->motivo_consulta,
                'descripcion_problema' => $this->psicologia_descripcion_problema,
                'red_apoyo' => $this->psicologia_red_apoyo,
                'plan_intervencion' => $this->psicologia_plan_intervencion_recomendacion,
                'tratamiento_actual' => $this->psicologia_tratamiento_actual_adherencia,
                'analisis_conclusiones' => $this->analisis_conclusiones,
                'comportamiento_consulta' => $this->psicologia_comportamiento_consulta
            ],
            
            // Evaluación nutricional
            'evaluacion_nutricional' => [
                'tolerancia_via_oral' => $this->tolerancia_via_oral,
                'percepcion_apetito' => $this->percepcion_apetito,
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
                'diagnostico_nutri' => $this->diagnostico_nutri
            ],
            
            // Horarios de comida
            'horarios_comida' => [
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
                ]
            ],
            
            // Frecuencia de consumo de alimentos
            'frecuencia_consumo' => [
                'lacteo' => [
                    'frecuencia' => $this->lacteo,
                    'observacion' => $this->lacteo_observacion
                ],
                'huevo' => [
                    'frecuencia' => $this->huevo,
                    'observacion' => $this->huevo_observacion
                ],
                'embutido' => [
                    'frecuencia' => $this->embutido,
                    'observacion' => $this->embutido_observacion
                ],
                'carnes' => [
                    'roja' => $this->carne_roja,
                    'blanca' => $this->carne_blanca,
                    'vicera' => $this->carne_vicera,
                    'observacion' => $this->carne_observacion
                ],
                'leguminosas' => [
                    'frecuencia' => $this->leguminosas,
                    'observacion' => $this->leguminosas_observacion
                ],
                'frutas' => [
                    'jugo' => $this->frutas_jugo,
                    'porcion' => $this->frutas_porcion,
                    'observacion' => $this->frutas_observacion
                ],
                'verduras_hortalizas' => [
                    'frecuencia' => $this->verduras_hortalizas,
                    'observacion' => $this->vh_observacion
                ],
                'cereales' => [
                    'frecuencia' => $this->cereales,
                    'observacion' => $this->cereales_observacion
                ],
                'azucar_dulce' => [
                    'frecuencia' => $this->azucar_dulce,
                    'observacion' => $this->ad_observacion
                ]
            ],
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString()
        ];
    }
}
