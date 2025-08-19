<?php
// app/Models/Estado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\{HasUuidTrait, SyncableTrait};

class Estado extends Model
{
    use HasFactory, SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $fillable = [
        'nombre'
    ];

    // Relaciones
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'estado_id');
    }

    // Métodos auxiliares
    public function esActivo(): bool
    {
        return strtoupper($this->nombre) === 'ACTIVO';
    }

    public function esInactivo(): bool
    {
        return strtoupper($this->nombre) === 'INACTIVO';
    }
}
