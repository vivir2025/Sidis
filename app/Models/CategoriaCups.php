<?php
// app/Models/CategoriaCups.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoriaCups extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categorias_cups';

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected $casts = [
        'nombre' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($categoria) {
            if (empty($categoria->uuid)) {
                $categoria->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function cupsContratados(): HasMany
    {
        return $this->hasMany(CupsContratado::class);
    }

    public function cups(): HasMany
    {
        return $this->hasMany(Cups::class, 'categoria_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%");
    }

    // Métodos auxiliares
    public function getTotalCupsAttribute()
    {
        return $this->cups()->count();
    }

    public function getTotalContratadosAttribute()
    {
        return $this->cupsContratados()->count();
    }
}
