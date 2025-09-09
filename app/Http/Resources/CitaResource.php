<?php
// app/Http/Resources/CitaResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d H:i:s'),
            'fecha_final' => $this->fecha_final?->format('Y-m-d H:i:s'),
            'fecha_deseada' => $this->fecha_deseada?->format('Y-m-d'),
            'motivo' => $this->motivo,
            'nota' => $this->nota,
            'estado' => $this->estado,
            'patologia' => $this->patologia,
               // ✅ UUIDs de relaciones
            'paciente_uuid' => $this->paciente_uuid,
            'agenda_uuid' => $this->agenda_uuid,
            'cups_contratado_uuid' => $this->cups_contratado_uuid,
            
            // Paciente
            'paciente' => $this->whenLoaded('paciente', function () {
                return [
                    'uuid' => $this->paciente->uuid,
                    'documento' => $this->paciente->documento,
                    'nombre_completo' => $this->paciente->nombre_completo,
                    'telefono' => $this->paciente->telefono,
                    'edad' => $this->paciente->edad,
                    'sexo' => $this->paciente->sexo
                ];
            }),
            
            // Agenda
            'agenda' => $this->whenLoaded('agenda', function () {
                return [
                    'uuid' => $this->agenda->uuid,
                    'modalidad' => $this->agenda->modalidad,
                    'consultorio' => $this->agenda->consultorio,
                    'hora_inicio' => $this->agenda->hora_inicio,
                    'hora_fin' => $this->agenda->hora_fin,
                    'fecha' => $this->agenda->fecha,
                    'usuario' => $this->whenLoaded('agenda.usuario', function () {
                        return [
                            'uuid' => $this->agenda->usuario->uuid,
                            'nombre_completo' => $this->agenda->usuario->nombre_completo,
                            'especialidad' => $this->agenda->usuario->especialidad?->nombre
                        ];
                    })
                ];
            }),
            
            // CUPS Contratado
            'cups_contratado' => $this->whenLoaded('cupsContratado', function () {
                return [
                    'uuid' => $this->cupsContratado->uuid,
                    'tarifa' => $this->cupsContratado->tarifa,
                    'cups' => $this->whenLoaded('cupsContratado.cups', function () {
                        return [
                            'uuid' => $this->cupsContratado->cups->uuid,
                            'codigo' => $this->cupsContratado->cups->codigo,
                            'nombre' => $this->cupsContratado->cups->nombre
                        ];
                    })
                ];
            }),
            
            // Usuario que creó la cita
            'usuario_creador' => $this->whenLoaded('usuarioCreador', function () {
                return [
                    'uuid' => $this->usuarioCreador->uuid,
                    'nombre_completo' => $this->usuarioCreador->nombre_completo
                ];
            }),
            
            // Historia clínica asociada
            'tiene_historia_clinica' => $this->whenLoaded('historiaClinica', function () {
                return !is_null($this->historiaClinica);
            }),
            
            'historia_clinica' => $this->whenLoaded('historiaClinica', function () {
                return $this->historiaClinica ? [
                    'uuid' => $this->historiaClinica->uuid,
                    'created_at' => $this->historiaClinica->created_at?->toISOString()
                ] : null;
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString()
        ];
    }
}
