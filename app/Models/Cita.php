<?php
// app/Models/Cita.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Cita extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'fecha',
        'fecha_inicio',
        'fecha_final',
        'fecha_deseada',
        'motivo',
        'nota',
        'estado',
        'patologia',
        'paciente_id', 
        'paciente_uuid',     
        'agenda_uuid',         
        'cups_contratado_uuid', 
        'usuario_creo_cita_id'  
    ];

    // ✅✅✅ CAMBIO CRÍTICO: Especificar formato de fechas ✅✅✅
    protected $casts = [
        'fecha' => 'date:Y-m-d',              // ← CAMBIO: Solo fecha
        'fecha_inicio' => 'datetime:Y-m-d H:i:s',  // ← CAMBIO: Fecha y hora
        'fecha_final' => 'datetime:Y-m-d H:i:s',   // ← CAMBIO: Fecha y hora
        'fecha_deseada' => 'date:Y-m-d'       // ← CAMBIO: Solo fecha
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($cita) {
            if (empty($cita->uuid)) {
                $cita->uuid = Str::uuid();
            }
        });
    }

    // ✅✅✅ NUEVO: Accessors para asegurar formato correcto ✅✅✅
    
    /**
     * Formatear fecha al obtenerla
     */
    public function getFechaAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        // Si ya es un string en formato Y-m-d, devolverlo tal cual
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Si es Carbon o DateTime, formatear
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formatear fecha_deseada al obtenerla
     */
    public function getFechaDeseadaAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        // Si ya es un string en formato Y-m-d, devolverlo tal cual
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Si es Carbon o DateTime, formatear
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formatear fecha_inicio al obtenerla (con hora)
     */
    public function getFechaInicioAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formatear fecha_final al obtenerla (con hora)
     */
    public function getFechaFinalAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    // ✅ RELACIONES USANDO UUIDs
    
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_uuid', 'uuid');
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class, 'agenda_uuid', 'uuid');
    }

    public function cupsContratado(): BelongsTo
    {
        return $this->belongsTo(CupsContratado::class, 'cups_contratado_uuid', 'uuid');
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_creo_cita_id');
    }

    public function pacientePorId(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'id');
    }

    /**
     * Método auxiliar para obtener paciente (UUID o ID)
     */
    public function obtenerPaciente()
    {
        // Primero intentar por UUID (sistema nuevo)
        if (!empty($this->paciente_uuid)) {
            $pacientePorUuid = $this->paciente()->first();
            if ($pacientePorUuid) {
                return $pacientePorUuid;
            }
        }

        // Si no funciona, intentar por ID (sistema legacy)
        if (!empty($this->paciente_id)) {
            return $this->pacientePorId()->first();
        }

        return null;
    }

    // ✅ SCOPES
    
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorPacienteUuid($query, $pacienteUuid)
    {
        return $query->where('paciente_uuid', $pacienteUuid);
    }

    public function scopeDelDia($query)
    {
        return $query->whereDate('fecha', now()->toDateString());
    }

    // ✅ MÉTODOS AUXILIARES
    
    /**
     * Obtener duración de la cita en minutos
     */
    public function getDuracionAttribute(): ?int
    {
        if (!$this->fecha_inicio || !$this->fecha_final) {
            return null;
        }

        try {
            $inicio = Carbon::parse($this->fecha_inicio);
            $final = Carbon::parse($this->fecha_final);
            return $inicio->diffInMinutes($final);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verificar si la cita está programada
     */
    public function getEsProgramadaAttribute(): bool
    {
        return $this->estado === 'PROGRAMADA';
    }

    /**
     * Verificar si la cita fue atendida
     */
    public function getEsAtendidaAttribute(): bool
    {
        return $this->estado === 'ATENDIDA';
    }

    /**
     * Verificar si la cita fue cancelada
     */
    public function getEsCanceladaAttribute(): bool
    {
        return $this->estado === 'CANCELADA';
    }
}
