<?php
// app/Models/Especialidad.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\{HasUuidTrait, SyncableTrait};

class Especialidad extends Model
{
    use HasFactory, SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'estado_id',
        'duracion_cita_minutos'
    ];

    protected $casts = [
        'duracion_cita_minutos' => 'integer'
    ];

    // Relaciones
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function medicos(): HasMany
    {
        return $this->hasMany(Usuario::class)->whereHas('rol', function ($q) {
            $q->where('nombre', 'MEDICO');
        });
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }

    // Accessors
    public function getNombreFormateadoAttribute(): string
    {
        return ucwords(strtolower($this->nombre));
    }

    // Métodos auxiliares
    public function estaActiva(): bool
    {
        return $this->estado && strtoupper($this->estado->nombre) === 'ACTIVO';
    }

    public function estaInactiva(): bool
    {
        return $this->estado && strtoupper($this->estado->nombre) === 'INACTIVO';
    }

    public function tieneMedicos(): bool
    {
        return $this->medicos()->exists();
    }

    public function cantidadMedicos(): int
    {
        return $this->medicos()->count();
    }

    public function cantidadMedicosActivos(): int
    {
        return $this->medicos()->whereHas('estado', function ($q) {
            $q->where('nombre', 'ACTIVO');
        })->count();
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->whereHas('estado', function ($q) {
            $q->where('nombre', 'ACTIVO');
        });
    }

    public function scopeInactivas($query)
    {
        return $query->whereHas('estado', function ($q) {
            $q->where('nombre', 'INACTIVO');
        });
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'LIKE', "%{$termino}%")
              ->orWhere('descripcion', 'LIKE', "%{$termino}%")
              ->orWhere('codigo', 'LIKE', "%{$termino}%");
        });
    }

    public function scopeConMedicos($query)
    {
        return $query->whereHas('medicos');
    }

    public function scopeOrdenadoPorNombre($query)
    {
        return $query->orderBy('nombre');
    }

    // Mutadores
    public function setNombreAttribute($value)
    {
        $this->attributes['nombre'] = strtoupper(trim($value));
    }

    public function setCodigoAttribute($value)
    {
        $this->attributes['codigo'] = strtoupper(trim($value));
    }
}
