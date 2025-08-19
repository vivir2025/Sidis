<?php
// app/Models/Role.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Generar UUID automáticamente
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    // Scopes y métodos auxiliares
    public function esAdministrador(): bool
    {
        return strtoupper($this->nombre) === 'ADMINISTRADOR';
    }

    public function esMedico(): bool
    {
        return strtoupper($this->nombre) === 'MEDICO';
    }

    public function esEnfermero(): bool
    {
        return strtoupper($this->nombre) === 'ENFERMERO';
    }
}
