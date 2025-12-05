<?php
// app/Http/Resources/HistoriaClinicaResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoriaClinicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'finalidad' => $this->finalidad,
            'motivo_consulta' => $this->motivo_consulta,
            'enfermedad_actual' => $this->enfermedad_actual,
            
            // Datos antropométricos
            'antropometria' => [
                'peso' => $this->peso,
                'talla' => $this->talla,
                'imc' => $this->imc,
                'clasificacion' => $this->clasificacion
            ],
            
            // Signos vitales
            'signos_vitales' => [
                'presion_arterial_sistolica_sentado_pie' => $this->presion_arterial_sistolica_sentado_pie,
                'presion_arterial_distolica_sentado_pie' => $this->presion_arterial_distolica_sentado_pie,
                'presion_arterial_sistolica_acostado' => $this->presion_arterial_sistolica_acostado,
                'presion_arterial_distolica_acostado' => $this->presion_arterial_distolica_acostado,
                'frecuencia_cardiaca' => $this->frecuencia_cardiaca,
                                'frecuencia_respiratoria' => $this->frecuencia_respiratoria
            ],
            
            // Acompañante
            'acompanante' => [
                'nombre' => $this->acompanante,
                'telefono' => $this->acu_telefono,
                'parentesco' => $this->acu_parentesco
            ],
            
            // Discapacidades
            'discapacidades' => [
                'fisica' => $this->discapacidad_fisica,
                'visual' => $this->discapacidad_visual,
                'mental' => $this->discapacidad_mental,
                'auditiva' => $this->discapacidad_auditiva,
                'intelectual' => $this->discapacidad_intelectual
            ],
            
            // Drogodependencia
            'drogodependencia' => [
                'dependiente' => $this->drogo_dependiente,
                'cual' => $this->drogo_dependiente_cual
            ],
            
            // Antecedentes familiares
            'antecedentes_familiares' => [
                'hipertension_arterial' => $this->hipertension_arterial,
                'parentesco_hipertension' => $this->parentesco_hipertension,
                'diabetes_mellitus' => $this->diabetes_mellitus,
                'parentesco_mellitus' => $this->parentesco_mellitus,
                'artritis' => $this->artritis,
                'parentesco_artritis' => $this->parentesco_artritis,
                'enfermedad_cardiovascular' => $this->enfermedad_cardiovascular,
                'parentesco_cardiovascular' => $this->parentesco_cardiovascular,
                'cancer_mama_estomago_prostata_colon' => $this->cancer_mama_estomago_prostata_colon,
                'parentesco_cancer' => $this->parentesco_cancer
            ],
            
            // Clasificaciones
            'clasificaciones' => [
                'hta' => $this->clasificacion_hta,
                'dm' => $this->clasificacion_dm,
                'erc_estado' => $this->clasificacion_erc_estado,
                'erc_estadodos' => $this->clasificacion_erc_estadodos,
                'rcv' => $this->clasificacion_rcv,
                'estado_metabolico' => $this->clasificacion_estado_metabolico
            ],
            
            // Educación brindada
            'educacion' => [
                'alimentacion' => $this->alimentacion,
                'disminucion_consumo_sal_azucar' => $this->disminucion_consumo_sal_azucar,
                'fomento_actividad_fisica' => $this->fomento_actividad_fisica,
                'importancia_adherencia_tratamiento' => $this->importancia_adherencia_tratamiento,
                'consumo_frutas_verduras' => $this->consumo_frutas_verduras,
                'manejo_estres' => $this->manejo_estres,
                'disminucion_consumo_cigarrillo' => $this->disminucion_consumo_cigarrillo,
                'disminucion_peso' => $this->disminucion_peso
            ],
            
            'observaciones_generales' => $this->observaciones_generales,
            'causa_externa' => $this->causa_externa,
            
            // Cita asociada
            'cita' => $this->whenLoaded('cita', function () {
                return [
                    'uuid' => $this->cita->uuid,
                    'fecha' => $this->cita->fecha?->format('Y-m-d'),
                    'fecha_inicio' => $this->cita->fecha_inicio?->format('Y-m-d H:i:s'),
                    'estado' => $this->cita->estado,
                    'paciente' => $this->whenLoaded('cita.paciente', function () {
                        return [
                            'uuid' => $this->cita->paciente->uuid,
                            'documento' => $this->cita->paciente->documento,
                            'nombre_completo' => $this->cita->paciente->nombre_completo,
                            'edad' => $this->cita->paciente->edad,
                            'sexo' => $this->cita->paciente->sexo
                        ];
                    }),
                    'agenda' => $this->whenLoaded('cita.agenda', function () {
                        return [
                            'uuid' => $this->cita->agenda->uuid,
                            'modalidad' => $this->cita->agenda->modalidad,
                            'usuario' => $this->whenLoaded('cita.agenda.usuario', function () {
                                return [
                                    'uuid' => $this->cita->agenda->usuario->uuid,
                                    'nombre_completo' => $this->cita->agenda->usuario->nombre_completo,
                                    'especialidad' => $this->cita->agenda->usuario->especialidad?->nombre
                                ];
                            })
                        ];
                    })
                ];
            }),
            
            // Historia complementaria
            'historia_complementaria' => $this->whenLoaded('historiaComplementaria', function () {
                return new HistoriaComplementariaResource($this->historiaComplementaria);
            }),
            
            // Diagnósticos
            'diagnosticos' => $this->whenLoaded('historiaDiagnosticos', function () {
                return $this->historiaDiagnosticos->map(function ($historiaDiagnostico) {
                    return [
                        'uuid' => $historiaDiagnostico->uuid,
                        'tipo' => $historiaDiagnostico->tipo,
                        'tipo_diagnostico' => $historiaDiagnostico->tipo_diagnostico,
                        'diagnostico' => $this->whenLoaded('historiaDiagnosticos.diagnostico', function () use ($historiaDiagnostico) {
                            return [
                                'uuid' => $historiaDiagnostico->diagnostico->uuid,
                                'codigo' => $historiaDiagnostico->diagnostico->codigo,
                                'nombre' => $historiaDiagnostico->diagnostico->nombre,
                                'categoria' => $historiaDiagnostico->diagnostico->categoria
                            ];
                        })
                    ];
                });
            }),
            
            // Medicamentos
            'medicamentos' => $this->whenLoaded('historiaMedicamentos', function () {
                return $this->historiaMedicamentos->map(function ($historiaMedicamento) {
                    return [
                        'uuid' => $historiaMedicamento->uuid,
                        'cantidad' => $historiaMedicamento->cantidad,
                        'dosis' => $historiaMedicamento->dosis,
                        'medicamento' => $this->whenLoaded('historiaMedicamentos.medicamento', function () use ($historiaMedicamento) {
                            return [
                                'uuid' => $historiaMedicamento->medicamento->uuid,
                                'nombre' => $historiaMedicamento->medicamento->nombre
                            ];
                        })
                    ];
                });
            }),
            
            // Remisiones
            'remisiones' => $this->whenLoaded('historiaRemisiones', function () {
                return $this->historiaRemisiones->map(function ($historiaRemision) {
                    return [
                        'uuid' => $historiaRemision->uuid,
                        'observacion' => $historiaRemision->observacion,
                        'remision' => $this->whenLoaded('historiaRemisiones.remision', function () use ($historiaRemision) {
                            return [
                                'uuid' => $historiaRemision->remision->uuid,
                                'codigo' => $historiaRemision->remision->codigo,
                                'nombre' => $historiaRemision->remision->nombre
                            ];
                        })
                    ];
                });
            }),
            
            // CUPS
            'cups' => $this->whenLoaded('historiaCups', function () {
                return $this->historiaCups->map(function ($historiaCup) {
                    return [
                        'uuid' => $historiaCup->uuid,
                        'observacion' => $historiaCup->observacion,
                        'cups' => $this->whenLoaded('historiaCups.cups', function () use ($historiaCup) {
                            return [
                                'uuid' => $historiaCup->cups->uuid,
                                'codigo' => $historiaCup->cups->codigo,
                                'nombre' => $historiaCup->cups->nombre
                            ];
                        })
                    ];
                });
            }),
            
            // PDFs adjuntos
            'pdfs' => $this->whenLoaded('pdfs', function () {
                return $this->pdfs->map(function ($pdf) {
                    return [
                        'uuid' => $pdf->uuid,
                        'archivo' => $pdf->archivo,
                        'observacion' => $pdf->observacion,
                        'created_at' => $pdf->created_at?->toISOString()
                    ];
                });
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString()
        ];
    }
}
