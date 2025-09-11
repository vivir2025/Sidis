<?php
// app/Models/HistoriaRemision.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HistoriaRemision extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historia_remisiones';

    protected $fillable = [
        'uuid', 'remision_id', 'historia_clinica_id', 'observacion', 
        'prioridad', 'estado', 'fecha_remision'
    ];

    protected $casts = ['fecha_remision' => 'date'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function remision()
    {
        return $this->belongsTo(Remision::class);
    }

    public function historiaClinica()
    {
        return $this->belongsTo(HistoriaClinica::class);
    }
}
