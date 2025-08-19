<?php
// app/Models/Cups.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Cups extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cups';

    protected $fillable = [
        'uuid',
        'origen',
        'nombre',
        'codigo',
        'estado'
    ];

    protected $casts = [
        'origen' => 'string',
        'nombre' => 'string',
        'codigo' => 'string',
        'estado' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($cups) {
            if (empty($cups->uuid)) {
                $cups->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function cupsContratados(): HasMany
    {
        return $this->hasMany(CupsContratado::class);
    }

    public function citas(): HasMany
    {
        return $this->hasManyThrough(Cita::class, CupsContratado::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeInactivos($query)
    {
        return $query->where('estado', 'INACTIVO');
    }

    public function scopePorCodigo($query, $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    public function scopePorOrigen($query, $origen)
    {
        return $query->where('origen', $origen);
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
              ->orWhere('codigo', 'like', "%{$termino}%");
        });
    }

    // Métodos auxiliares
    public function getTotalContratosAttribute()
    {
        return $this->cupsContratados()->count();
    }

    public function getTotalCitasAttribute()
    {
        return $this->citas()->count();
    }

    public function getEsActivoAttribute()
    {
        return $this->estado === 'ACTIVO';
    }

    // En las relaciones, agregar:
public function categoria(): BelongsTo
{
    return $this->belongsTo(CategoriaCups::class, 'categoria_cups_id');
}
}
