<?php
// app/Models/Escolaridad.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Escolaridad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'escolaridades';

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($escolaridad) {
            if (empty($escolaridad->uuid)) {
                $escolaridad->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
