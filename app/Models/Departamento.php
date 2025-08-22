<?php
// app/Models/Departamento.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Departamento extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($departamento) {
            if (empty($departamento->uuid)) {
                $departamento->uuid = Str::uuid();
            }
        });
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }

    public function pacientesNacimiento(): HasMany
    {
        return $this->hasMany(Paciente::class, 'depto_nacimiento_id');
    }

    public function pacientesResidencia(): HasMany
    {
        return $this->hasMany(Paciente::class, 'depto_residencia_id');
    }
}
