<?php
// app/Models/HcsParaclinico.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HcsParaclinico extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'fecha',
        'identificacion',
        
        // Perfil lipídico
        'colesterol_total',
        'colesterol_hdl',
        'trigliceridos',
        'colesterol_ldl',
        
        // Hematología
        'hemoglobina',
        'hematocrito',
        'plaquetas',
        
        // Glucemia
        'hemoglobina_glicosilada',
        'glicemia_basal',
        'glicemia_post',
        
        // Función renal
        'creatinina',
        'creatinuria',
        'microalbuminuria',
        'albumina',
        'relacion_albuminuria_creatinuria',
        'parcial_orina',
        'depuracion_creatinina',
        'creatinina_orina_24',
        'proteina_orina_24',
        
        // Hormonas
        'hormona_estimulante_tiroides',
        'hormona_paratiroidea',
        
        // Química sanguínea
        'albumina_suero',
        'fosforo_suero',
        'nitrogeno_ureico',
        'acido_urico_suero',
        'calcio',
        'sodio_suero',
        'potasio_suero',
        
        // Hierro
        'hierro_total',
        'ferritina',
        'transferrina',
        
        // Enzimas
        'fosfatasa_alcalina',
        
        // Vitaminas
        'acido_folico_suero',
        'vitamina_b12',
        
        'nitrogeno_ureico_orina_24'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($paraclinico) {
            if (empty($paraclinico->uuid)) {
                $paraclinico->uuid = Str::uuid();
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

    // Métodos auxiliares
    public function getPerfilLipidicoAttribute()
    {
        return [
            'colesterol_total' => $this->colesterol_total,
            'colesterol_hdl' => $this->colesterol_hdl,
            'colesterol_ldl' => $this->colesterol_ldl,
            'trigliceridos' => $this->trigliceridos,
        ];
    }

    public function getHematologiaAttribute()
    {
        return [
            'hemoglobina' => $this->hemoglobina,
            'hematocrito' => $this->hematocrito,
            'plaquetas' => $this->plaquetas,
        ];
    }

    public function getGlucemiaAttribute()
    {
        return [
            'hemoglobina_glicosilada' => $this->hemoglobina_glicosilada,
            'glicemia_basal' => $this->glicemia_basal,
            'glicemia_post' => $this->glicemia_post,
        ];
    }

    public function getFuncionRenalAttribute()
    {
        return [
            'creatinina' => $this->creatinina,
            'creatinuria' => $this->creatinuria,
            'microalbuminuria' => $this->microalbuminuria,
            'albumina' => $this->albumina,
            'relacion_albuminuria_creatinuria' => $this->relacion_albuminuria_creatinuria,
            'parcial_orina' => $this->parcial_orina,
            'depuracion_creatinina' => $this->depuracion_creatinina,
            'creatinina_orina_24' => $this->creatinina_orina_24,
            'proteina_orina_24' => $this->proteina_orina_24,
        ];
    }

    public function getHormonasAttribute()
    {
        return [
            'hormona_estimulante_tiroides' => $this->hormona_estimulante_tiroides,
            'hormona_paratiroidea' => $this->hormona_paratiroidea,
        ];
    }

    public function getQuimicaSanguineaAttribute()
    {
        return [
            'albumina_suero' => $this->albumina_suero,
            'fosforo_suero' => $this->fosforo_suero,
            'nitrogeno_ureico' => $this->nitrogeno_ureico,
            'acido_urico_suero' => $this->acido_urico_suero,
            'calcio' => $this->calcio,
            'sodio_suero' => $this->sodio_suero,
            'potasio_suero' => $this->potasio_suero,
        ];
    }

    public function getHierroAttribute()
    {
        return [
            'hierro_total' => $this->hierro_total,
            'ferritina' => $this->ferritina,
            'transferrina' => $this->transferrina,
        ];
    }

    public function getVitaminasAttribute()
    {
        return [
            'acido_folico_suero' => $this->acido_folico_suero,
            'vitamina_b12' => $this->vitamina_b12,
        ];
    }

    // Validar si tiene valores anormales (ejemplo básico)
    public function getValoresAnormalesAttribute()
    {
        $anormales = [];
        
        // Ejemplo de rangos normales (estos deberían venir de configuración)
        $rangosNormales = [
            'colesterol_total' => ['min' => 0, 'max' => 200],
            'glicemia_basal' => ['min' => 70, 'max' => 100],
            'hemoglobina' => ['min' => 12, 'max' => 16],
            'creatinina' => ['min' => 0.6, 'max' => 1.2],
        ];

        foreach ($rangosNormales as $campo => $rango) {
            $valor = floatval($this->$campo);
            if ($valor > 0 && ($valor < $rango['min'] || $valor > $rango['max'])) {
                $anormales[] = $campo;
            }
        }

        return $anormales;
    }
}
