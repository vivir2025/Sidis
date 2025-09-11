<?php
// app/Models/HistoriaDiagnostico.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HistoriaDiagnostico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historia_diagnosticos';

    protected $fillable = [
        'uuid', 'historia_clinica_id', 'diagnostico_id', 
        'tipo', 'tipo_diagnostico', 'observacion'
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

    public function diagnostico()
    {
        return $this->belongsTo(Diagnostico::class);
    }

    public function scopePrincipal($query)
    {
        return $query->where('tipo_diagnostico', 'PRINCIPAL');
    }
}
