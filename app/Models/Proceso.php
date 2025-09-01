<?php
// app/Models/Brigada.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brigada extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($brigada) {
            if (empty($brigada->uuid)) {
                $brigada->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function agendas()
    {
        return $this->hasMany(Agenda::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'brigada_usuario');
    }

    // Scopes
    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%");
    }

    // Métodos auxiliares
    public function getAgendasActivasAttribute()
    {
        return $this->agendas()->activas()->count();
    }
}
