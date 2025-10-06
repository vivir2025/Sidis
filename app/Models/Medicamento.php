<?php
// app/Models/Medicamento.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Medicamento extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'codigo', 'nombre', 'principio_activo', 'concentracion',
        'forma_farmaceutica', 'via_administracion', 'unidad_medida', 
        'pos', 'activo'
    ];

    protected $casts = [
        'pos' => 'boolean',
        'activo' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($medicamento) {
            if (empty($medicamento->uuid)) {
                $medicamento->uuid = Str::uuid();
            }
        });
    }

    public function historiaMedicamentos()
    {
        return $this->hasMany(HistoriaMedicamento::class);
    }

  

    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('principio_activo', 'like', "%{$termino}%");
    }
}
