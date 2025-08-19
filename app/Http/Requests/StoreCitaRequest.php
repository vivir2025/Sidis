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
            'paciente_id' => 'required|exists:pacientes,id',
            'agenda_id' => 'required|exists:agendas,id',
            'cups_contratado_id' => 'required|exists:cups_contratados,id'
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
            'paciente_id.required' => 'El paciente es obligatorio.',
            'agenda_id.required' => 'La agenda es obligatoria.',
            'cups_contratado_id.required' => 'El CUPS contratado es obligatorio.'
        ];
    }
}
