<?php
// app/Http/Resources/CitaResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            
            // ✅ FECHAS CORREGIDAS - Verificación de tipo antes de formatear
            'fecha' => $this->formatDate($this->fecha, 'Y-m-d'),
            'fecha_inicio' => $this->formatDate($this->fecha_inicio, 'Y-m-d H:i:s'),
            'fecha_final' => $this->formatDate($this->fecha_final, 'Y-m-d H:i:s'),
            'fecha_deseada' => $this->formatDate($this->fecha_deseada, 'Y-m-d'),
            
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
                    'fecha' => $this->formatDate($this->agenda->fecha, 'Y-m-d'),
                    'usuario' => $this->when(
                        $this->agenda && $this->agenda->relationLoaded('usuario'),
                        function () {
                            return [
                                'uuid' => $this->agenda->usuario->uuid,
                                'nombre_completo' => $this->agenda->usuario->nombre_completo,
                                'especialidad' => $this->agenda->usuario->especialidad?->nombre
                            ];
                        }
                    )
                ];
            }),
            
            // CUPS Contratado
            'cups_contratado' => $this->whenLoaded('cupsContratado', function () {
                return [
                    'uuid' => $this->cupsContratado->uuid,
                    'tarifa' => $this->cupsContratado->tarifa,
                    'cups' => $this->when(
                        $this->cupsContratado && $this->cupsContratado->relationLoaded('cups'),
                        function () {
                            return [
                                'uuid' => $this->cupsContratado->cups->uuid,
                                'codigo' => $this->cupsContratado->cups->codigo,
                                'nombre' => $this->cupsContratado->cups->nombre
                            ];
                        }
                    )
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
                    'created_at' => $this->formatDate($this->historiaClinica->created_at, 'c')
                ] : null;
            }),
            
            'created_at' => $this->formatDate($this->created_at, 'c'),
            'updated_at' => $this->formatDate($this->updated_at, 'c')
        ];
    }

    /**
     * ✅ Método helper para formatear fechas de forma segura
     * 
     * @param mixed $date
     * @param string $format
     * @return string|null
     */
    private function formatDate($date, string $format = 'Y-m-d'): ?string
    {
        if (!$date) {
            return null;
        }
        
        // Si ya es una instancia de Carbon
        if ($date instanceof Carbon) {
            return $date->format($format);
        }
        
        // Si es una instancia de DateTime
        if ($date instanceof \DateTime) {
            return Carbon::instance($date)->format($format);
        }
        
        // Si es un string, intentar parsearlo
        if (is_string($date)) {
            try {
                return Carbon::parse($date)->format($format);
            } catch (\Exception $e) {
                \Log::warning('Error al parsear fecha en CitaResource', [
                    'fecha' => $date,
                    'error' => $e->getMessage()
                ]);
                return $date; // Devolver el valor original si falla
            }
        }
        
        return null;
    }
}
