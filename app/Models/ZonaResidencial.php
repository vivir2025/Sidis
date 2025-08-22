<?php
// app/Models/ZonaResidencial.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ZonaResidencial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'zonas_residenciales';

    protected $fillable = [
        'uuid',
        'abreviacion',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($zonaResidencial) {
            if (empty($zonaResidencial->uuid)) {
                $zonaResidencial->uuid = Str::uuid();
            }
        });
    }

    public function pacientesResidencia(): HasMany
    {
        return $this->hasMany(Paciente::class, 'zona_residencia_id');
    }
}
