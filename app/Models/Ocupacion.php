<?php
// app/Models/Ocupacion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ocupacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ocupaciones';

    protected $fillable = [
        'uuid',
        'codigo',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ocupacion) {
            if (empty($ocupacion->uuid)) {
                $ocupacion->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
