<?php
// app/Models/CupsContratado.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CupsContratado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cups_contratados';

    protected $fillable = [
        'uuid',
        'contrato_id',
        'categoria_cups_id',
        'cups_id',
        'tarifa',
        'estado'
    ];

    protected $casts = [
        'tarifa' => 'string',
        'estado' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($cupsContratado) {
            if (empty($cupsContratado->uuid)) {
                $cupsContratado->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function categoriaCups(): BelongsTo
    {
        return $this->belongsTo(CategoriaCups::class);
    }

    public function cups(): BelongsTo
    {
        return $this->belongsTo(Cups::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
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

    public function scopePorContrato($query, $contratoId)
    {
        return $query->where('contrato_id', $contratoId);
    }

    public function scopePorCategoria($query, $categoriaId)
    {
        return $query->where('categoria_cups_id', $categoriaId);
    }

    public function scopePorCups($query, $cupsId)
    {
        return $query->where('cups_id', $cupsId);
    }

    public function scopeConRelaciones($query)
    {
        return $query->with(['contrato', 'categoriaCups', 'cups']);
    }

    // Métodos auxiliares
    public function getTarifaNumericaAttribute()
    {
        return floatval(str_replace([',', '$', ' '], '', $this->tarifa));
    }

    public function getTarifaFormateadaAttribute()
    {
        $valor = $this->tarifa_numerica;
        return '$' . number_format($valor, 0, ',', '.');
    }

    public function getTotalCitasAttribute()
    {
        return $this->citas()->count();
    }

    public function getEsActivoAttribute()
    {
        return $this->estado === 'ACTIVO';
    }

    public function getDescripcionCompletaAttribute()
    {
        return "{$this->cups->codigo} - {$this->cups->nombre} ({$this->tarifa_formateada})";
    }

    // Validar disponibilidad
    public function estaDisponible()
    {
        return $this->estado === 'ACTIVO' && 
               $this->contrato->estado === 'ACTIVO' && 
               $this->contrato->es_vigente;
    }
}
