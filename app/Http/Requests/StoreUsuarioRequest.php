<?php
// app/Http/Requests/StoreUsuarioRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sede_id' => 'required|exists:sedes,id',
            'documento' => 'required|string|max:15|unique:usuarios,documento',
            'nombre' => 'required|string|max:50',
            'apellido' => 'required|string|max:50',
            'telefono' => 'required|string|max:10',
            'correo' => 'required|email|max:60|unique:usuarios,correo',
            'login' => 'required|string|max:50|unique:usuarios,login',
            'password' => 'required|string|min:6|confirmed',
            'rol_id' => 'required|exists:roles,id',
            'estado_id' => 'required|exists:estados,id',
            'especialidad_id' => 'nullable|required_if:es_medico,true|exists:especialidades,id',
            'registro_profesional' => 'nullable|required_if:es_medico,true|string|max:50',
            'firma' => 'nullable|string',
            'firma_file' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'El documento ya está registrado',
            'correo.unique' => 'El correo ya está registrado',
            'login.unique' => 'El login ya está en uso',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'especialidad_id.required_if' => 'La especialidad es obligatoria para médicos',
            'registro_profesional.required_if' => 'El registro profesional es obligatorio para médicos',
        ];
    }
}
