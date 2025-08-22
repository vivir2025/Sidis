<?php
// app/Models/TipoParentesco.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TipoParentesco extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_parentesco';

    protected $fillable = [
        'uuid',
        'nombre'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tipoParentesco) {
            if (empty($tipoParentesco->uuid)) {
                $tipoParentesco->uuid = Str::uuid();
            }
        });
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'parentesco_id');
    }
}
