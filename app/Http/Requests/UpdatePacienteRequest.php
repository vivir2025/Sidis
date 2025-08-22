<?php
// app/Http/Requests/UpdatePacienteRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Obtener el paciente actual por UUID
        $paciente = \App\Models\Paciente::where('uuid', $this->route('uuid'))->first();
        
        return [
            'empresa_id' => 'sometimes|exists:empresas,id',
            'regimen_id' => 'sometimes|exists:regimenes,id',
            'tipo_afiliacion_id' => 'sometimes|exists:tipos_afiliacion,id',
            'zona_residencia_id' => 'sometimes|exists:zonas_residenciales,id',
            'depto_nacimiento_id' => 'sometimes|exists:departamentos,id',
            'depto_residencia_id' => 'sometimes|exists:departamentos,id',
            'municipio_nacimiento_id' => 'sometimes|exists:municipios,id',
            'municipio_residencia_id' => 'sometimes|exists:municipios,id',
            'raza_id' => 'sometimes|exists:razas,id',
            'escolaridad_id' => 'sometimes|exists:escolaridades,id',
            'parentesco_id' => 'nullable|exists:tipos_parentesco,id',
            'tipo_documento_id' => 'sometimes|exists:tipos_documento,id',
            'ocupacion_id' => 'nullable|exists:ocupaciones,id',
            'novedad_id' => 'nullable|exists:novedades,id',
            'auxiliar_id' => 'nullable|exists:auxiliares,id',
            'brigada_id' => 'nullable|exists:brigadas,id',
            
            'registro' => 'sometimes|string|max:50',
            'primer_nombre' => 'sometimes|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'sometimes|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
            'documento' => [
                'sometimes',
                'string',
                'max:13',
                Rule::unique('pacientes')->ignore($paciente?->id)
            ],
            'fecha_nacimiento' => 'sometimes|date|before:today',
            'sexo' => 'sometimes|in:M,F',
            'direccion' => 'sometimes|string|max:100',
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
            
            'estado' => 'sometimes|in:ACTIVO,INACTIVO',
            'fecha_deseada' => 'nullable|date'
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'Ya existe un paciente con este número de documento.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'regimen_id.exists' => 'El régimen seleccionado no existe.',
            'tipo_afiliacion_id.exists' => 'El tipo de afiliación seleccionado no existe.',
            'zona_residencia_id.exists' => 'La zona de residencia seleccionada no existe.',
            'depto_nacimiento_id.exists' => 'El departamento de nacimiento seleccionado no existe.',
            'depto_residencia_id.exists' => 'El departamento de residencia seleccionado no existe.',
            'municipio_nacimiento_id.exists' => 'El municipio de nacimiento seleccionado no existe.',
            'municipio_residencia_id.exists' => 'El municipio de residencia seleccionado no existe.',
            'raza_id.exists' => 'La raza seleccionada no existe.',
            'escolaridad_id.exists' => 'La escolaridad seleccionada no existe.',
            'tipo_documento_id.exists' => 'El tipo de documento seleccionado no existe.',
            'sexo.in' => 'El sexo debe ser M (Masculino) o F (Femenino).',
            'estado.in' => 'El estado debe ser ACTIVO o INACTIVO.',
            'estado_civil.in' => 'El estado civil debe ser uno de los valores permitidos.',
            'correo.email' => 'El correo debe tener un formato válido.'
        ];
    }
}
