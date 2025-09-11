<?php
// app/Models/Remision.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Remision extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'remisiones';

    protected $fillable = [
        'uuid', 'codigo', 'nombre', 'tipo', 'especialidad_id', 
        'descripcion', 'activo'
    ];

    protected $casts = ['activo' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($remision) {
            if (empty($remision->uuid)) {
                $remision->uuid = Str::uuid();
            }
        });
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function historiaRemisiones()
    {
        return $this->hasMany(HistoriaRemision::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
