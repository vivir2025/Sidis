<?php
// app/Models/TipoDocumento.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TipoDocumento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_documento';

    protected $fillable = [
        'uuid',
        'abreviacion',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tipoDocumento) {
            if (empty($tipoDocumento->uuid)) {
                $tipoDocumento->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
