<?php
// app/Http/Requests/StorePacienteRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'regimen_id' => 'required|exists:regimenes,id',
            'tipo_afiliacion_id' => 'required|exists:tipos_afiliacion,id',
            'zona_residencia_id' => 'required|exists:zonas_residenciales,id',
            'depto_nacimiento_id' => 'required|exists:departamentos,id',
            'depto_residencia_id' => 'required|exists:departamentos,id',
            'municipio_nacimiento_id' => 'required|exists:municipios,id',
            'municipio_residencia_id' => 'required|exists:municipios,id',
            'raza_id' => 'required|exists:razas,id',
            'escolaridad_id' => 'required|exists:escolaridades,id',
            'parentesco_id' => 'nullable|exists:tipos_parentesco,id',
            'tipo_documento_id' => 'required|exists:tipos_documento,id',
            'ocupacion_id' => 'nullable|exists:ocupaciones,id',
            'novedad_id' => 'nullable|exists:novedades,id',
            'auxiliar_id' => 'nullable|exists:auxiliares,id',
            'brigada_id' => 'nullable|exists:brigadas,id',
            
            'registro' => 'nullable|string|max:50',
            'primer_nombre' => 'required|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'required|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
            'documento' => 'required|string|max:13|unique:pacientes,documento',
            'fecha_nacimiento' => 'required|date|before:today',
            'sexo' => 'required|in:M,F',
            'direccion' => 'required|string|max:100',
            'telefono' => 'nullable|string|max:12',
            'correo' => 'nullable|email|max:100',
            'observacion' => 'nullable|string|max:500',
            'estado_civil' => 'nullable|in:SOLTERO,CASADO,UNION_LIBRE,DIVORCIADO,VIUDO,SEPARADO',
            
            // Acudiente
            'nombre_acudiente' => 'nullable|string|max:100',
            'parentesco_acudiente' => 'nullable|string|max:50',
            'telefono_acudiente' => 'nullable|string|max:12',
            'direccion_acudiente' => 'nullable|string|max:100',
            
            // Acompañante
            'acompanante_nombre' => 'nullable|string|max:100',
            'acompanante_telefono' => 'nullable|string|max:12',
            
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
            'fecha_deseada' => 'nullable|date'
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'Ya existe un paciente con este número de documento.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'primer_nombre.required' => 'El primer nombre es obligatorio.',
            'primer_apellido.required' => 'El primer apellido es obligatorio.',
            'empresa_id.required' => 'La empresa es obligatoria.',
            'regimen_id.required' => 'El régimen es obligatorio.',
            'tipo_afiliacion_id.required' => 'El tipo de afiliación es obligatorio.',
            'zona_residencia_id.required' => 'La zona de residencia es obligatoria.',
            'depto_nacimiento_id.required' => 'El departamento de nacimiento es obligatorio.',
            'depto_residencia_id.required' => 'El departamento de residencia es obligatorio.',
            'municipio_nacimiento_id.required' => 'El municipio de nacimiento es obligatorio.',
            'municipio_residencia_id.required' => 'El municipio de residencia es obligatorio.',
            'raza_id.required' => 'La raza es obligatoria.',
            'escolaridad_id.required' => 'La escolaridad es obligatoria.',
            'tipo_documento_id.required' => 'El tipo de documento es obligatorio.',
            'sexo.required' => 'El sexo es obligatorio.',
            'direccion.required' => 'La dirección es obligatoria.'
        ];
    }
}
