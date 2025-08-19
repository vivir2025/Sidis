<?php
// app/Models/Factura.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Factura extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'cita_id',
        'paciente_id',
        'contrato_id',
        'fecha',
        'copago',
        'comision',
        'descuento',
        'valor_consulta',
        'sub_total',
        'autorizacion',
        'cantidad'
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($factura) {
            if (empty($factura->uuid)) {
                $factura->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    // Scopes
    public function scopePorSede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        }
        return $query->whereDate('fecha', $fechaInicio);
    }

    public function scopePorPaciente($query, $pacienteId)
    {
        return $query->where('paciente_id', $pacienteId);
    }

    public function scopePorContrato($query, $contratoId)
    {
        return $query->where('contrato_id', $contratoId);
    }

    public function scopeDelMes($query, $mes = null, $año = null)
    {
        $mes = $mes ?: now()->month;
        $año = $año ?: now()->year;
        
        return $query->whereMonth('fecha', $mes)
                    ->whereYear('fecha', $año);
    }

    // Métodos auxiliares - Convertir strings a números
    public function getCopagoNumericoAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->copago));
    }

    public function getComisionNumericaAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->comision));
    }

    public function getDescuentoNumericoAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->descuento));
    }

    public function getValorConsultaNumericoAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->valor_consulta));
    }

    public function getSubTotalNumericoAttribute()
    {
        return floatval(str_replace([',', '$'], '', $this->sub_total));
    }

    // Calcular total con descuentos
    public function getTotalFinalAttribute()
    {
        $subTotal = $this->sub_total_numerico;
        $descuento = $this->descuento_numerico;
        $copago = $this->copago_numerico;
        
        return $subTotal - $descuento + $copago;
    }

    // Calcular comisión sobre el valor
    public function getComisionCalculadaAttribute()
    {
        $valorConsulta = $this->valor_consulta_numerico;
        $porcentajeComision = $this->comision_numerica;
        
        return ($valorConsulta * $porcentajeComision) / 100;
    }

    // Verificar si tiene autorización
    public function getTieneAutorizacionAttribute()
    {
        return !empty($this->autorizacion) && $this->autorizacion !== '0';
    }

    // Obtener información del paciente
    public function getNombrePacienteAttribute()
    {
        return $this->paciente ? $this->paciente->nombre_completo : null;
    }

    public function getDocumentoPacienteAttribute()
    {
        return $this->paciente ? $this->paciente->documento : null;
    }

    // Obtener información del contrato
    public function getNumeroContratoAttribute()
    {
        return $this->contrato ? $this->contrato->numero : null;
    }

    public function getEmpresaAttribute()
    {
        return $this->contrato ? $this->contrato->empresa : null;
    }
}
