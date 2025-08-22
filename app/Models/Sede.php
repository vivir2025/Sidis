<?php
// app/Models/Sede.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasUuidTrait;

class Sede extends Model
{
    use HasUuidTrait;

    protected $fillable = [
        'nombre',
        'codigo',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function historiasClinicas(): HasMany
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function syncQueue(): HasMany
    {
        return $this->hasMany(SyncQueue::class);
    }
       
    public function paraclinicos(): HasMany
    {
        return $this->hasMany(HcsParaclinico::class);
    }
        
    public function visitas(): HasMany
    {
        return $this->hasMany(HcsVisita::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }


}
