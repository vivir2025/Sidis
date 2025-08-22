<?php
// app/Models/Raza.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Raza extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($raza) {
            if (empty($raza->uuid)) {
                $raza->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
