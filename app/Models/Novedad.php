<?php
// app/Models/Novedad.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Novedad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'novedades';

    protected $fillable = [
        'uuid',
        'tipo_novedad'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($novedad) {
            if (empty($novedad->uuid)) {
                $novedad->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
