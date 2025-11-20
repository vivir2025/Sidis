<?php
// app/Http/Requests/StoreCitaRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            
            // ✅ CORRECCIÓN: Validar que el paciente exista y no esté eliminado
            'paciente_uuid' => [
                'required',
                'string',
                Rule::exists('pacientes', 'uuid')
                    ->whereNull('deleted_at')
            ],
            
            // ✅ CORRECCIÓN PRINCIPAL: Validar que la agenda exista, esté ACTIVA y no eliminada
            'agenda_uuid' => [
                'required',
                'string',
                Rule::exists('agendas', 'uuid')
                    ->where('estado', 'ACTIVO')  // ← ✅ CAMBIO CRÍTICO
                    ->whereNull('deleted_at')
            ],
            
            // ✅ Validar CUPS contratado
            'cups_contratado_uuid' => [
                'nullable',
                'string',
                Rule::exists('cups_contratados', 'uuid')
                    ->whereNull('deleted_at')
            ],
            
            // ✅ Validar sede (si tienes este campo en tu tabla)
            'sede_id' => 'nullable|integer|exists:sedes,id',
            
            // ✅ Validar usuario creador
            'usuario_creo_cita_id' => 'nullable|integer|exists:usuarios,id',
        ];
    }

    public function messages(): array
    {
        return [
            // Mensajes de fecha
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser válida.',
            
            'fecha_final.required' => 'La fecha final es obligatoria.',
            'fecha_final.date' => 'La fecha final debe ser válida.',
            'fecha_final.after' => 'La fecha final debe ser posterior a la fecha de inicio.',
            
            'fecha_deseada.date' => 'La fecha deseada debe ser una fecha válida.',
            
            // Mensajes de campos requeridos
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres.',
            
            'nota.max' => 'La nota no puede exceder 1000 caracteres.',
            
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: PROGRAMADA, EN_CURSO, FINALIZADA, CANCELADA o NO_ASISTIO.',
            
            'patologia.max' => 'La patología no puede exceder 200 caracteres.',
            
            // Mensajes de relaciones
            'paciente_uuid.required' => 'El paciente es obligatorio.',
            'paciente_uuid.exists' => 'El paciente seleccionado no existe o fue eliminado.',
            
            'agenda_uuid.required' => 'La agenda es obligatoria.',
            'agenda_uuid.exists' => 'La agenda seleccionada no existe, no está activa o fue eliminada.',
            
            'cups_contratado_uuid.exists' => 'El CUPS seleccionado no existe o fue eliminado.',
            
            'sede_id.exists' => 'La sede seleccionada no existe.',
            
            'usuario_creo_cita_id.exists' => 'El usuario creador no existe.',
        ];
    }

    /**
     * ✅ Preparar datos antes de la validación
     * Útil si necesitas transformar datos antes de validar
     */
    protected function prepareForValidation()
    {
        // Extraer sede_id si viene dentro de sede_actual
        if ($this->has('sede_actual') && is_array($this->sede_actual)) {
            $sedeActual = $this->sede_actual;
            
            // Si viene como ['App\Models\Sede' => [...]]
            if (isset($sedeActual['App\\Models\\Sede'])) {
                $sedeData = $sedeActual['App\\Models\\Sede'];
                $this->merge([
                    'sede_id' => $sedeData['id'] ?? null
                ]);
            }
            // Si viene directamente como array con 'id'
            elseif (isset($sedeActual['id'])) {
                $this->merge([
                    'sede_id' => $sedeActual['id']
                ]);
            }
        }

        // Asegurar que usuario_creo_cita_id tenga un valor
        if (!$this->has('usuario_creo_cita_id') || empty($this->usuario_creo_cita_id)) {
            $this->merge([
                'usuario_creo_cita_id' => auth()->id()
            ]);
        }
    }

    /**
     * ✅ Personalizar mensajes de error de validación
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_final' => 'fecha final',
            'fecha_deseada' => 'fecha deseada',
            'motivo' => 'motivo',
            'nota' => 'nota',
            'estado' => 'estado',
            'patologia' => 'patología',
            'paciente_uuid' => 'paciente',
            'agenda_uuid' => 'agenda',
            'cups_contratado_uuid' => 'CUPS contratado',
            'sede_id' => 'sede',
            'usuario_creo_cita_id' => 'usuario creador',
        ];
    }
}
