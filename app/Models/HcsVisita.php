<?php
// app/Models/HcsVisita.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HcsVisita extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'fecha',
        'identificacion',
        'edad',
        'hta',
        'dm',
        'telefono',
        'zona',
        
        // Medidas antropométricas
        'peso',
        'talla',
        'imc',
        'perimetro_abdominal',
        
        // Signos vitales
        'frecuencia_cardiaca',
        'frecuencia_respiratoria',
        'tension_arterial',
        'glucometria',
        'temperatura',
        
        // Información social
        'familiar',
        'abandono_social',
        
        // Evaluación
        'motivo',
        'medicamentos',
        'riesgos',
        'conductas',
        'novedades',
        'encargado',
        'fecha_control',
        
        // Documentación
        'foto',
        'firma'
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_control' => 'date',
        'perimetro_abdominal' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($visita) {
            if (empty($visita->uuid)) {
                $visita->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'identificacion', 'numero_documento');
    }

    // Scopes
    public function scopePorSede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopePorPaciente($query, $identificacion)
    {
        return $query->where('identificacion', $identificacion);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        }
        return $query->whereDate('fecha', $fechaInicio);
    }

    public function scopeRecientes($query, $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    public function scopeConHta($query)
    {
        return $query->where('hta', 'SI');
    }

    public function scopeConDm($query)
    {
        return $query->where('dm', 'SI');
    }

    public function scopePorZona($query, $zona)
    {
        return $query->where('zona', 'like', "%{$zona}%");
    }

    public function scopeConAbandonoSocial($query)
    {
        return $query->where('abandono_social', 'SI');
    }

    // Métodos auxiliares
    public function getMedidasAntropometricasAttribute()
    {
        return [
            'peso' => $this->peso,
            'talla' => $this->talla,
            'imc' => $this->imc,
            'perimetro_abdominal' => $this->perimetro_abdominal,
        ];
    }

    public function getSignosVitalesAttribute()
    {
        return [
            'frecuencia_cardiaca' => $this->frecuencia_cardiaca,
            'frecuencia_respiratoria' => $this->frecuencia_respiratoria,
            'tension_arterial' => $this->tension_arterial,
            'glucometria' => $this->glucometria,
            'temperatura' => $this->temperatura,
        ];
    }

    public function getInformacionSocialAttribute()
    {
        return [
            'familiar' => $this->familiar,
            'abandono_social' => $this->abandono_social,
            'zona' => $this->zona,
        ];
    }

    public function getEvaluacionAttribute()
    {
        return [
            'motivo' => $this->motivo,
            'medicamentos' => $this->medicamentos,
            'riesgos' => $this->riesgos,
            'conductas' => $this->conductas,
            'novedades' => $this->novedades,
            'encargado' => $this->encargado,
            'fecha_control' => $this->fecha_control,
        ];
    }

    public function getDocumentacionAttribute()
    {
        return [
            'foto' => $this->foto,
            'firma' => $this->firma,
        ];
    }

    // Calcular IMC si no está presente
    public function calcularImc()
    {
        if ($this->peso && $this->talla) {
            $peso = floatval($this->peso);
            $talla = floatval($this->talla) / 100; // convertir cm a metros
            
            if ($talla > 0) {
                return round($peso / ($talla * $talla), 2);
            }
        }
        return null;
    }

    // Clasificar IMC
    public function getClasificacionImcAttribute()
    {
        $imc = floatval($this->imc) ?: $this->calcularImc();
        
        if (!$imc) return null;

        if ($imc < 18.5) return 'Bajo peso';
        if ($imc < 25) return 'Normal';
        if ($imc < 30) return 'Sobrepeso';
        if ($imc < 35) return 'Obesidad grado I';
        if ($imc < 40) return 'Obesidad grado II';
        return 'Obesidad grado III';
    }

    // Evaluar riesgo cardiovascular
    public function getRiesgoCardiovascularAttribute()
    {
        $factores = 0;
        
        if ($this->hta === 'SI') $factores++;
        if ($this->dm === 'SI') $factores++;
        
        $imc = floatval($this->imc) ?: $this->calcularImc();
        if ($imc && $imc >= 30) $factores++;
        
        if ($this->perimetro_abdominal) {
            // Valores de riesgo según género (asumiendo criterios generales)
            if ($this->perimetro_abdominal > 88) $factores++; // Para mujeres
        }

        if ($factores >= 3) return 'Alto';
        if ($factores >= 2) return 'Moderado';
        if ($factores >= 1) return 'Bajo';
        return 'Mínimo';
    }

    // Verificar si necesita control próximo
    public function getNecesitaControlAttribute()
    {
        if (!$this->fecha_control) return false;
        
        return $this->fecha_control <= now()->addDays(7);
    }

    // Obtener días hasta el próximo control
    public function getDiasHastaControlAttribute()
    {
        if (!$this->fecha_control) return null;
        
        return now()->diffInDays($this->fecha_control, false);
    }
}
