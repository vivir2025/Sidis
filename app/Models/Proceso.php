<?php
// app/Models/Proceso.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Proceso extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre',
        'n_cups'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($proceso) {
            if (empty($proceso->uuid)) {
                $proceso->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function agendas()
    {
        return $this->hasMany(Agenda::class);
    }

    public function citas()
    {
        return $this->hasManyThrough(Cita::class, Agenda::class);
    }

    // Scopes
    public function scopeConCups($query)
    {
        return $query->whereNotNull('n_cups');
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('n_cups', 'like', "%{$termino}%");
    }
}
