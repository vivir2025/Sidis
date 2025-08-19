<?php
// app/Models/Cita.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'agenda_id',
        'cups_contratado_id',
        'usuario_creo_cita_id'
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_inicio' => 'datetime',
        'fecha_final' => 'datetime',
        'fecha_deseada' => 'date'
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

    // ✅ RELACIONES CORREGIDAS
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class);
    }

    public function cupsContratado(): BelongsTo
    {
        return $this->belongsTo(CupsContratado::class);
    }

    // ✅ CAMBIAR ESTA RELACIÓN - Debe coincidir con el nombre usado en el controlador
    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_creo_cita_id');
    }

    // ✅ AGREGAR SCOPE PARA SEDE
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    // Scopes adicionales
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorPaciente($query, $pacienteId)
    {
        return $query->where('paciente_id', $pacienteId);
    }

    public function scopeDelDia($query)
    {
        return $query->whereDate('fecha', now()->toDateString());
    }

    // Métodos auxiliares
    public function getDuracionAttribute()
    {
        return $this->fecha_inicio->diffInMinutes($this->fecha_final);
    }

    public function getEsProgramadaAttribute()
    {
        return $this->estado === 'PROGRAMADA';
    }

    public function getEsAtendidaAttribute()
    {
        return $this->estado === 'ATENDIDA';
    }

    public function getEsCanceladaAttribute()
    {
        return $this->estado === 'CANCELADA';
    }
}
