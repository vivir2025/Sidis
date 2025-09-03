<?php
// app/Models/Agenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agenda extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'modalidad',
        'fecha',
        'consultorio',
        'hora_inicio',
        'hora_fin',
        'intervalo',
        'etiqueta',
        'estado',
        'proceso_id',
        'usuario_id',
        'brigada_id'
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
    ];

    protected $appends = ['cupos_disponibles', 'total_cupos'];


    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($agenda) {
            if (empty($agenda->uuid)) {
                $agenda->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function proceso()
    {
        return $this->belongsTo(Proceso::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function brigada()
    {
        return $this->belongsTo(Brigada::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopePorSede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'ACTIVO')
                    ->where('fecha', '>=', now()->toDateString());
    }

   

    public function getEstaLlenaAttribute()
    {
        return $this->cupos_disponibles <= 0;
    }
    public function getCuposDisponiblesAttribute()
{
    try {
        $totalCupos = $this->total_cupos;
        $citasOcupadas = $this->citas()
            ->whereNotIn('estado', ['CANCELADA', 'NO_ASISTIO'])
            ->count();
        
        return max(0, $totalCupos - $citasOcupadas);
    } catch (\Exception $e) {
        return 0;
    }
}

public function getTotalCuposAttribute()
{
    try {
        $inicio = \Carbon\Carbon::parse($this->hora_inicio);
        $fin = \Carbon\Carbon::parse($this->hora_fin);
        $intervalo = (int) ($this->intervalo ?? 15);
        
        if ($intervalo <= 0) return 0;
        
        $duracionMinutos = $fin->diffInMinutes($inicio);
        return floor($duracionMinutos / $intervalo);
    } catch (\Exception $e) {
        return 0;
    }
}
}
