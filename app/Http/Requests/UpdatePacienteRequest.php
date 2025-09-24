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
            // ✅ CAMPOS OBLIGATORIOS
            'primer_nombre' => 'required|string|max:50',
            'primer_apellido' => 'required|string|max:50',
            'documento' => [
                'required',
                'string',
                'max:20',
                Rule::unique('pacientes')->ignore($paciente?->id)
            ],
            'fecha_nacimiento' => 'required|date|before:today',
            'sexo' => 'required|in:M,F',
            
            // ✅ CAMPOS OPCIONALES BÁSICOS
            'segundo_nombre' => 'nullable|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:100',
            'estado_civil' => 'nullable|string|max:50',
            'observacion' => 'nullable|string',
            'registro' => 'nullable|string|max:50',
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
            
            // ✅ IDs DE RELACIONES - COMO STRINGS (UUIDs)
            'tipo_documento_id' => 'nullable|string',
            'empresa_id' => 'nullable|string',
            'regimen_id' => 'nullable|string',
            'tipo_afiliacion_id' => 'nullable|string',
            'zona_residencia_id' => 'nullable|string',
            'depto_nacimiento_id' => 'nullable|string',
            'depto_residencia_id' => 'nullable|string',
            'municipio_nacimiento_id' => 'nullable|string',
            'municipio_residencia_id' => 'nullable|string',
            'raza_id' => 'nullable|string',
            'escolaridad_id' => 'nullable|string',
            'parentesco_id' => 'nullable|string',
            'ocupacion_id' => 'nullable|string',
            'novedad_id' => 'nullable|string',
            'auxiliar_id' => 'nullable|string',
            'brigada_id' => 'nullable|string',
            
            // ✅ CAMPOS ADICIONALES
            'nombre_acudiente' => 'nullable|string|max:100',
            'parentesco_acudiente' => 'nullable|string|max:50',
            'telefono_acudiente' => 'nullable|string|max:50',
            'direccion_acudiente' => 'nullable|string|max:255',
            'acompanante_nombre' => 'nullable|string|max:100',
            'acompanante_telefono' => 'nullable|string|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'primer_nombre.required' => 'El primer nombre es obligatorio.',
            'primer_apellido.required' => 'El primer apellido es obligatorio.',
            'documento.required' => 'El documento es obligatorio.',
            'documento.unique' => 'Ya existe un paciente con este número de documento.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'sexo.required' => 'El sexo es obligatorio.',
            'sexo.in' => 'El sexo debe ser M (Masculino) o F (Femenino).',
            'estado.in' => 'El estado debe ser ACTIVO o INACTIVO.',
            'correo.email' => 'El correo debe tener un formato válido.'
        ];
    }
}
