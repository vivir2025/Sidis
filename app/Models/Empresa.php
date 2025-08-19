<?php
// app/Models/Empresa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre',
        'nit',
        'codigo_eapb',
        'codigo',
        'direccion',
        'telefono',
        'estado'
    ];

    protected $casts = [
        'estado' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($empresa) {
            if (empty($empresa->uuid)) {
                $empresa->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeInactivas($query)
    {
        return $query->where('estado', 'INACTIVO');
    }

    public function scopePorNit($query, $nit)
    {
        return $query->where('nit', $nit);
    }

    public function scopePorCodigo($query, $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // Métodos auxiliares
    public function getContratosActivosAttribute()
    {
        return $this->contratos()->where('estado', 'ACTIVO')->get();
    }

    public function getTotalPacientesAttribute()
    {
        return $this->pacientes()->count();
    }
}
