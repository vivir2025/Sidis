<?php
// app/Models/TipoAfiliacion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TipoAfiliacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_afiliacion';

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tipoAfiliacion) {
            if (empty($tipoAfiliacion->uuid)) {
                $tipoAfiliacion->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
