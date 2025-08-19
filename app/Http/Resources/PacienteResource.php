<?php
// app/Http/Resources/PacienteResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'registro' => $this->registro,
            'documento' => $this->documento,
            'tipo_documento' => $this->whenLoaded('tipoDocumento', function () {
                return [
                    'uuid' => $this->tipoDocumento->uuid,
                    'abreviacion' => $this->tipoDocumento->abreviacion,
                    'nombre' => $this->tipoDocumento->nombre
                ];
            }),
            'nombre_completo' => $this->nombre_completo,
            'primer_nombre' => $this->primer_nombre,
            'segundo_nombre' => $this->segundo_nombre,
            'primer_apellido' => $this->primer_apellido,
            'segundo_apellido' => $this->segundo_apellido,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'edad' => $this->edad,
            'sexo' => $this->sexo,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'estado' => $this->estado,
            'estado_civil' => $this->estado_civil,
            'observacion' => $this->observacion,
            
            // Relaciones
            'empresa' => $this->whenLoaded('empresa', function () {
                return [
                    'uuid' => $this->empresa->uuid,
                    'nombre' => $this->empresa->nombre,
                    'nit' => $this->empresa->nit,
                    'codigo_eapb' => $this->empresa->codigo_eapb
                ];
            }),
            
            'regimen' => $this->whenLoaded('regimen', function () {
                return [
                    'uuid' => $this->regimen->uuid,
                    'nombre' => $this->regimen->nombre
                ];
            }),
            
            'tipo_afiliacion' => $this->whenLoaded('tipoAfiliacion', function () {
                return [
                    'uuid' => $this->tipoAfiliacion->uuid,
                    'nombre' => $this->tipoAfiliacion->nombre
                ];
            }),
            
            'zona_residencia' => $this->whenLoaded('zonaResidencia', function () {
                return [
                    'uuid' => $this->zonaResidencia->uuid,
                    'nombre' => $this->zonaResidencia->nombre,
                    'abreviacion' => $this->zonaResidencia->abreviacion
                ];
            }),
            
            'departamento_nacimiento' => $this->whenLoaded('departamentoNacimiento', function () {
                return [
                    'uuid' => $this->departamentoNacimiento->uuid,
                    'nombre' => $this->departamentoNacimiento->nombre
                ];
            }),
            
            'departamento_residencia' => $this->whenLoaded('departamentoResidencia', function () {
                return [
                    'uuid' => $this->departamentoResidencia->uuid,
                    'nombre' => $this->departamentoResidencia->nombre
                ];
            }),
            
            'municipio_nacimiento' => $this->whenLoaded('municipioNacimiento', function () {
                return [
                    'uuid' => $this->municipioNacimiento->uuid,
                    'nombre' => $this->municipioNacimiento->nombre
                ];
            }),
            
            'municipio_residencia' => $this->whenLoaded('municipioResidencia', function () {
                return [
                    'uuid' => $this->municipioResidencia->uuid,
                    'nombre' => $this->municipioResidencia->nombre
                ];
            }),
            
            'raza' => $this->whenLoaded('raza', function () {
                return [
                    'uuid' => $this->raza->uuid,
                    'nombre' => $this->raza->nombre
                ];
            }),
            
            'escolaridad' => $this->whenLoaded('escolaridad', function () {
                return [
                    'uuid' => $this->escolaridad->uuid,
                    'nombre' => $this->escolaridad->nombre
                ];
            }),
            
            'ocupacion' => $this->whenLoaded('ocupacion', function () {
                return [
                    'uuid' => $this->ocupacion->uuid,
                    'codigo' => $this->ocupacion->codigo,
                    'nombre' => $this->ocupacion->nombre
                ];
            }),
            
            // Acudiente
            'acudiente' => [
                'nombre' => $this->nombre_acudiente,
                'parentesco' => $this->parentesco_acudiente,
                'telefono' => $this->telefono_acudiente,
                'direccion' => $this->direccion_acudiente
            ],
            
            // Acompañante
            'acompanante' => [
                'nombre' => $this->acompanante_nombre,
                'telefono' => $this->acompanante_telefono
            ],
            
            // Fechas
            'fecha_registro' => $this->fecha_registro?->format('Y-m-d'),
            'fecha_actualizacion' => $this->fecha_actualizacion?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Estadísticas (cuando se cargan)
            'total_citas' => $this->whenCounted('citas'),
            'total_historias' => $this->whenCounted('historiasClinicas'),
            
            // Citas recientes (cuando se cargan)
            'citas_recientes' => CitaResource::collection($this->whenLoaded('citas')),
            'historias_recientes' => HistoriaClinicaResource::collection($this->whenLoaded('historiasClinicas'))
        ];
    }
}
