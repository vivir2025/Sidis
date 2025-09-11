<?php
// app/Models/Diagnostico.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Diagnostico extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'codigo', 'nombre', 'categoria', 'subcategoria', 
        'descripcion', 'activo'
    ];

    protected $casts = ['activo' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($diagnostico) {
            if (empty($diagnostico->uuid)) {
                $diagnostico->uuid = Str::uuid();
            }
        });
    }

    // Relaciones
    public function historiaDiagnosticos()
    {
        return $this->hasMany(HistoriaDiagnostico::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
              ->orWhere('nombre', 'like', "%{$termino}%");
        });
    }
}
