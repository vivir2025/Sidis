<?php
// app/Models/Municipio.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
use Illuminate\Support\Str;

class Municipio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre',
        'departamento_id'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($municipio) {
            if (empty($municipio->uuid)) {
                $municipio->uuid = Str::uuid();
            }
        });
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function pacientesNacimiento(): HasMany
    {
        return $this->hasMany(Paciente::class, 'municipio_nacimiento_id');
    }

    public function pacientesResidencia(): HasMany
    {
        return $this->hasMany(Paciente::class, 'municipio_residencia_id');
    }
}
