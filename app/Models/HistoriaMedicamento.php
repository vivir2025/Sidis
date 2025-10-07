<?php
// app/Models/HistoriaMedicamento.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HistoriaMedicamento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historia_medicamentos';

    // ✅ SOLO CAMPOS QUE EXISTEN EN TU MIGRACIÓN
    protected $fillable = [
        'uuid', 
        'medicamento_id', 
        'historia_clinica_id', 
        'cantidad', 
        'dosis'
        // ❌ REMOVER ESTOS CAMPOS QUE NO EXISTEN:
        // 'frecuencia', 'duracion', 'via_administracion', 'observaciones'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class);
    }

    public function historiaClinica()
    {
        return $this->belongsTo(HistoriaClinica::class);
    }
}
