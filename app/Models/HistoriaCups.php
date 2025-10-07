<?php
// app/Models/HistoriaCups.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HistoriaCups extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historia_cups';

    // ✅ SOLO CAMPOS QUE EXISTEN EN TU MIGRACIÓN
    protected $fillable = [
        'uuid', 
        'historia_clinica_id', 
        'cups_id', 
        'observacion'
        // ❌ REMOVER ESTOS CAMPOS QUE NO EXISTEN:
        // 'cantidad', 'estado'
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

    public function historiaClinica()
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function cups()
    {
        return $this->belongsTo(Cups::class);
    }
}
