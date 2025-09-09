<?php
// app/Http/Requests/StoreCitaRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required|date',
            'fecha_inicio' => 'required|date',
            'fecha_final' => 'required|date|after:fecha_inicio',
            'fecha_deseada' => 'nullable|date',
            'motivo' => 'required|string|max:500',
            'nota' => 'nullable|string|max:1000',
            'estado' => 'required|in:PROGRAMADA,EN_CURSO,FINALIZADA,CANCELADA,NO_ASISTIO',
            'patologia' => 'nullable|string|max:200',
            'paciente_uuid' => 'required|string|exists:pacientes,uuid',
            'agenda_uuid' => 'required|string|exists:agendas,uuid',
            'cups_contratado_uuid' => 'nullable|string|exists:cups_contratados,uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_final.required' => 'La fecha final es obligatoria.',
            'fecha_final.after' => 'La fecha final debe ser posterior a la fecha de inicio.',
            'motivo.required' => 'El motivo es obligatorio.',
            'estado.required' => 'El estado es obligatorio.',
            'paciente_uuid.required' => 'El paciente es obligatorio',
            'paciente_uuid.exists' => 'El paciente seleccionado no existe',
             'agenda_uuid.required' => 'La agenda es obligatoria',
            'agenda_uuid.exists' => 'La agenda seleccionada no existe',
            'cups_contratado_uuid.exists' => 'El CUPS seleccionado no existe',
        ];
    }
}
