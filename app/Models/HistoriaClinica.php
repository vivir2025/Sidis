<?php
// app/Models/HistoriaClinica.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\{HasUuidTrait, SyncableTrait};

class HistoriaClinica extends Model
{
    use SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $fillable = [
        'sede_id', 'cita_id', 'finalidad', 'acompanante', 'acu_telefono',
        'acu_parentesco', 'causa_externa', 'motivo_consulta', 'enfermedad_actual',
        'discapacidad_fisica', 'discapacidad_visual', 'discapacidad_mental',
        'discapacidad_auditiva', 'discapacidad_intelectual', 'drogo_dependiente',
        'drogo_dependiente_cual', 'peso', 'talla', 'imc', 'clasificacion',
        'tasa_filtracion_glomerular_ckd_epi', 'tasa_filtracion_glomerular_gockcroft_gault',
        // ... todos los campos de la migración
        'observaciones_generales', 'clasificacion_estado_metabolico',
        'fex_es', 'fex_es1', 'fex_es2'
    ];

    // Relaciones
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function historiaComplementaria(): HasOne
    {
        return $this->hasOne(HistoriaClinicaComplementaria::class);
    }

    public function historiaCups(): HasMany
    {
        return $this->hasMany(HistoriaCups::class);
    }

    public function historiaDiagnosticos(): HasMany
    {
        return $this->hasMany(HistoriaDiagnostico::class);
    }

    public function historiaMedicamentos(): HasMany
    {
        return $this->hasMany(HistoriaMedicamento::class);
    }

    public function historiaRemisiones(): HasMany
    {
        return $this->hasMany(HistoriaRemision::class);
    }

    public function pdfs(): HasMany
    {
        return $this->hasMany(HcPdf::class);
    }

    // Accessors
    public function getPacienteAttribute()
    {
        return $this->cita->paciente;
    }

    // Scopes
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }
}
