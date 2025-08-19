<?php
// app/Models/Usuario.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\{HasUuidTrait, SyncableTrait};

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $fillable = [
        'sede_id', 'documento', 'nombre', 'apellido', 'telefono', 'correo',
        'registro_profesional', 'firma', 'login', 'password', 'estado_id',
        'rol_id', 'especialidad_id'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed'
    ];

    // Relaciones
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }

    public function citasCreadas(): HasMany
    {
        return $this->hasMany(Cita::class, 'usuario_creo_cita_id');
    }

    // Accessors
    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    // Métodos auxiliares basados en rol
    public function esAdministrador(): bool
    {
        return $this->rol && strtoupper($this->rol->nombre) === 'ADMINISTRADOR';
    }

    public function esMedico(): bool
    {
        return $this->rol && strtoupper($this->rol->nombre) === 'MEDICO';
    }

    public function esEnfermero(): bool
    {
        return $this->rol && strtoupper($this->rol->nombre) === 'ENFERMERO';
    }

    public function esSecretaria(): bool
    {
        return $this->rol && strtoupper($this->rol->nombre) === 'SECRETARIA';
    }

    public function esAuxiliar(): bool
    {
        return $this->rol && strtoupper($this->rol->nombre) === 'AUXILIAR';
    }

    // Métodos auxiliares basados en estado
    public function estaActivo(): bool
    {
        return $this->estado && strtoupper($this->estado->nombre) === 'ACTIVO';
    }

    public function estaInactivo(): bool
    {
        return $this->estado && strtoupper($this->estado->nombre) === 'INACTIVO';
    }

    public function estaSuspendido(): bool
    {
        return $this->estado && strtoupper($this->estado->nombre) === 'SUSPENDIDO';
    }

    // Método para obtener permisos del usuario
    public function getPermisosAttribute(): array
    {
        return [
            'es_administrador' => $this->esAdministrador(),
            'es_medico' => $this->esMedico(),
            'es_enfermero' => $this->esEnfermero(),
            'es_secretaria' => $this->esSecretaria(),
            'es_auxiliar' => $this->esAuxiliar(),
            'puede_crear_citas' => $this->esAdministrador() || $this->esSecretaria(),
            'puede_ver_agenda' => $this->esAdministrador() || $this->esMedico() || $this->esEnfermero(),
            'puede_gestionar_usuarios' => $this->esAdministrador(),
        ];
    }

    // Scopes existentes
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopeActivos($query)
    {
        return $query->whereHas('estado', function ($q) {
            $q->where('nombre', 'ACTIVO');
        });
    }

    // Scopes adicionales por rol
    public function scopeMedicos($query)
    {
        return $query->whereHas('rol', function ($q) {
            $q->where('nombre', 'MEDICO');
        });
    }

    public function scopeAdministradores($query)
    {
        return $query->whereHas('rol', function ($q) {
            $q->where('nombre', 'ADMINISTRADOR');
        });
    }

    public function scopeEnfermeros($query)
    {
        return $query->whereHas('rol', function ($q) {
            $q->where('nombre', 'ENFERMERO');
        });
    }

    public function scopeByRol($query, $rolNombre)
    {
        return $query->whereHas('rol', function ($q) use ($rolNombre) {
            $q->where('nombre', strtoupper($rolNombre));
        });
    }

    // Método para verificar si tiene permisos específicos
    public function tienePermiso(string $permiso): bool
    {
        $permisos = $this->permisos;
        return $permisos[$permiso] ?? false;
    }

    // Método para obtener el nombre del rol
    public function getNombreRolAttribute(): ?string
    {
        return $this->rol?->nombre;
    }

    // Método para obtener el nombre del estado
    public function getNombreEstadoAttribute(): ?string
    {
        return $this->estado?->nombre;
    }

    // Relación con pacientes (cuando la tabla exista)
public function pacientes(): HasMany
{
    return $this->hasMany(Paciente::class, 'medico_id');
}

// Relación con citas como médico
public function citasComoMedico(): HasMany
{
    return $this->hasMany(Cita::class, 'medico_id');
}

// Método para obtener especialidad formateada
public function getNombreEspecialidadAttribute(): ?string
{
    return $this->especialidad?->nombre_formateado;
}

// Scope por especialidad
public function scopePorEspecialidad($query, $especialidadId)
{
    return $query->where('especialidad_id', $especialidadId);
}

// Método para verificar si tiene especialidad
public function tieneEspecialidad(): bool
{
    return !is_null($this->especialidad_id) && $this->especialidad;
}
}
