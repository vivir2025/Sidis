<?php
// app/Models/Contrato.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contrato extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'empresa_id',
        'numero',
        'descripcion',
        'plan_beneficio',
        'poliza',
        'por_descuento',
        'fecha_inicio',
        'fecha_fin',
        'valor',
        'fecha_registro',
        'tipo',
        'copago',
        'estado'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_registro' => 'date',
        'plan_beneficio' => 'string',
        'tipo' => 'string',
        'copago' => 'string',
        'estado' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($contrato) {
            if (empty($contrato->uuid)) {
                $contrato->uuid = Str::uuid();
            }
            if (empty($contrato->fecha_registro)) {
                $contrato->fecha_registro = now();
            }
        });
    }

    // Relaciones
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
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

    public function scopeVigentes($query)
    {
        return $query->where('fecha_inicio', '<=', now())
                    ->where('fecha_fin', '>=', now());
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeConCopago($query)
    {
        return $query->where('copago', 'SI');
    }

    public function scopeSinCopago($query)
    {
        return $query->where('copago', 'NO');
    }

    // Métodos auxiliares
    public function getEsVigenteAttribute()
    {
        return $this->fecha_inicio <= now() && $this->fecha_fin >= now();
    }

    public function getDiasVigenciaAttribute()
    {
        if (!$this->es_vigente) return 0;
        return now()->diffInDays($this->fecha_fin);
    }

    public function getValorNumericoAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->valor));
    }

    public function getPorcentajeDescuentoAttribute()
    {
        return floatval($this->por_descuento);
    }

    public function getTotalFacturasAttribute()
    {
        return $this->facturas()->count();
    }

    public function getMontoFacturadoAttribute()
    {
        return $this->facturas()->sum('sub_total');
    }
}
