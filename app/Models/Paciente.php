<?php
// app/Models/Paciente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Paciente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'sede_id',
        'empresa_id',
        'regimen_id',
        'tipo_afiliacion_id',
        'zona_residencia_id',
        'depto_nacimiento_id',
        'depto_residencia_id',
        'municipio_nacimiento_id',
        'municipio_residencia_id',
        'raza_id',
        'escolaridad_id',
        'parentesco_id',
        'tipo_documento_id',
        'registro',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'documento',
        'fecha_nacimiento',
        'sexo',
        'direccion',
        'telefono',
        'correo',
        'observacion',
        'estado_civil',
        'ocupacion_id',
        'nombre_acudiente',
        'parentesco_acudiente',
        'telefono_acudiente',
        'direccion_acudiente',
        'estado',
        'acompanante_nombre',
        'acompanante_telefono',
        'fecha_registro',
        'novedad_id',
        'auxiliar_id',
        'brigada_id',
        'fecha_actualizacion'
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_registro' => 'date',
        'fecha_actualizacion' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($paciente) {
            if (empty($paciente->uuid)) {
                $paciente->uuid = Str::uuid();
            }
        });
    }

    // Relaciones BelongsTo
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function regimen(): BelongsTo
    {
        return $this->belongsTo(Regimen::class);
    }

    public function tipoAfiliacion(): BelongsTo
    {
        return $this->belongsTo(TipoAfiliacion::class, 'tipo_afiliacion_id');
    }

    public function zonaResidencia(): BelongsTo
    {
        return $this->belongsTo(ZonaResidencial::class, 'zona_residencia_id');
    }

    public function departamentoNacimiento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'depto_nacimiento_id');
    }

    public function departamentoResidencia(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'depto_residencia_id');
    }

    public function municipioNacimiento(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_nacimiento_id');
    }

    public function municipioResidencia(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_residencia_id');
    }

    public function raza(): BelongsTo
    {
        return $this->belongsTo(Raza::class);
    }

    public function escolaridad(): BelongsTo
    {
        return $this->belongsTo(Escolaridad::class);
    }

    public function tipoParentesco(): BelongsTo
    {
        return $this->belongsTo(TipoParentesco::class, 'parentesco_id');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function ocupacion(): BelongsTo
    {
        return $this->belongsTo(Ocupacion::class);
    }

    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class);
    }

    public function auxiliar(): BelongsTo
    {
        return $this->belongsTo(Auxiliar::class);
    }

    public function brigada(): BelongsTo
    {
        return $this->belongsTo(Brigada::class);
    }

    // Relaciones HasMany (si existen otras tablas)
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function historiasClinicas(): HasMany
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    // Accessors
    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento ? Carbon::parse($this->fecha_nacimiento)->age : null;
    }

    public function getNombreCompletoAttribute()
    {
        $nombre = trim($this->primer_nombre . ' ' . ($this->segundo_nombre ?? ''));
        $apellido = trim($this->primer_apellido . ' ' . ($this->segundo_apellido ?? ''));
        return trim($nombre . ' ' . $apellido);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopePorSede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopePorDocumento($query, $documento)
    {
        return $query->where('documento', $documento);
    }
}
