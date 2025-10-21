<?php
// app/Http/Resources/PacienteResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'registro' => $this->registro,
            'documento' => $this->documento,
            
            // ✅ IDs DIRECTOS PARA EL FORMULARIO
            'tipo_documento_id' => $this->tipoDocumento?->uuid,
            'empresa_id' => $this->empresa?->uuid,
            'regimen_id' => $this->regimen?->uuid,
            'tipo_afiliacion_id' => $this->tipoAfiliacion?->uuid,
            'zona_residencia_id' => $this->zonaResidencia?->uuid,
            'depto_nacimiento_id' => $this->departamentoNacimiento?->uuid,
            'depto_residencia_id' => $this->departamentoResidencia?->uuid,
            'municipio_nacimiento_id' => $this->municipioNacimiento?->uuid,
            'municipio_residencia_id' => $this->municipioResidencia?->uuid,
            'raza_id' => $this->raza?->uuid,
            'escolaridad_id' => $this->escolaridad?->uuid,
            'parentesco_id' => $this->tipoParentesco?->uuid,
            'ocupacion_id' => $this->ocupacion?->uuid,
            'novedad_id' => $this->novedad?->uuid ?? null,
            'auxiliar_id' => $this->auxiliar?->uuid ?? null,
            'brigada_id' => $this->brigada?->uuid ?? null,
            
            // ✅ CAMPOS DE ACUDIENTE Y ACOMPAÑANTE
            'nombre_acudiente' => $this->nombre_acudiente,
            'parentesco_acudiente' => $this->parentesco_acudiente,
            'telefono_acudiente' => $this->telefono_acudiente,
            'direccion_acudiente' => $this->direccion_acudiente,
            'acompanante_nombre' => $this->acompanante_nombre,
            'acompanante_telefono' => $this->acompanante_telefono,
            
            // ✅ DATOS BÁSICOS
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
            
            // ✅ FECHA DE NACIMIENTO CORREGIDA
            'fecha_nacimiento' => $this->formatDate($this->fecha_nacimiento, 'Y-m-d'),
            
            'edad' => $this->edad,
            'sexo' => $this->sexo,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'estado' => $this->estado,
            'estado_civil' => $this->estado_civil,
            'observacion' => $this->observacion,
            
            // ✅ RELACIONES EXISTENTES
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
            
            'parentesco' => $this->whenLoaded('tipoParentesco', function () {
                return [
                    'uuid' => $this->tipoParentesco->uuid,
                    'nombre' => $this->tipoParentesco->nombre
                ];
            }),
            
            // ✅ RELACIONES ADMINISTRATIVAS
            'novedad' => $this->whenLoaded('novedad', function () {
                return [
                    'uuid' => $this->novedad->uuid,
                    'tipo_novedad' => $this->novedad->tipo_novedad
                ];
            }),
            
            'auxiliar' => $this->whenLoaded('auxiliar', function () {
                return [
                    'uuid' => $this->auxiliar->uuid,
                    'nombre' => $this->auxiliar->nombre
                ];
            }),
            
            'brigada' => $this->whenLoaded('brigada', function () {
                return [
                    'uuid' => $this->brigada->uuid,
                    'nombre' => $this->brigada->nombre
                ];
            }),
            
            // ✅ ESTRUCTURA ANIDADA PARA COMPATIBILIDAD
            'acudiente' => [
                'nombre' => $this->nombre_acudiente,
                'parentesco' => $this->parentesco_acudiente,
                'telefono' => $this->telefono_acudiente,
                'direccion' => $this->direccion_acudiente
            ],
            
            'acompanante' => [
                'nombre' => $this->acompanante_nombre,
                'telefono' => $this->acompanante_telefono
            ],
            
            // ✅ FECHAS CORREGIDAS
            'fecha_registro' => $this->formatDate($this->fecha_registro, 'Y-m-d'),
            'fecha_actualizacion' => $this->formatDate($this->fecha_actualizacion, 'Y-m-d'),
            'created_at' => $this->formatDate($this->created_at, 'c'),
            'updated_at' => $this->formatDate($this->updated_at, 'c'),
            
            // Estadísticas
            'total_citas' => $this->whenCounted('citas'),
            'total_historias' => $this->whenCounted('historiasClinicas'),
            
            'citas_recientes' => $this->whenLoaded('citas', function () {
                return $this->citas->take(5)->map(function ($cita) {
                    return [
                        'uuid' => $cita->uuid ?? null,
                        'fecha' => $this->formatDate($cita->fecha, 'Y-m-d'),
                        'estado' => $cita->estado,
                        'motivo' => $cita->motivo
                    ];
                });
            }),
            
            'historias_recientes' => $this->whenLoaded('historiasClinicas', function () {
                return $this->historiasClinicas->take(5)->map(function ($historia) {
                    return [
                        'uuid' => $historia->uuid ?? null,
                        'fecha' => $this->formatDate($historia->fecha, 'Y-m-d'),
                        'tipo' => $historia->tipo ?? 'CONSULTA'
                    ];
                });
            })
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
                \Log::warning('Error al parsear fecha en PacienteResource', [
                    'fecha' => $date,
                    'error' => $e->getMessage()
                ]);
                return $date; // Devolver el valor original si falla
            }
        }
        
        return null;
    }
}
