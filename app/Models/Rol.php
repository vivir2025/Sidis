<?php
// app/Models/Rol.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\{HasUuidTrait, SyncableTrait};

class Rol extends Model
{
    use HasFactory, SoftDeletes, HasUuidTrait, SyncableTrait;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion', // Campo adicional opcional
        'activo'       // Campo adicional opcional
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Constantes para los roles (mejor práctica)
    public const ADMINISTRADOR = 'ADMINISTRADOR';
    public const MEDICO = 'MEDICO';
    public const ENFERMERO = 'ENFERMERO';
    public const SECRETARIA = 'SECRETARIA';
    public const AUXILIAR = 'AUXILIAR';

    // Array de roles válidos
    public static function getRolesValidos(): array
    {
        return [
            self::ADMINISTRADOR,
            self::MEDICO,
            self::ENFERMERO,
            self::SECRETARIA,
            self::AUXILIAR
        ];
    }

    // Relaciones
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    public function usuariosActivos(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id')
                    ->whereHas('estado', function ($query) {
                        $query->where('nombre', 'ACTIVO');
                    });
    }

    // Métodos auxiliares existentes
    public function esAdministrador(): bool
    {
        return strtoupper($this->nombre) === self::ADMINISTRADOR;
    }

    public function esMedico(): bool
    {
        return strtoupper($this->nombre) === self::MEDICO;
    }

    public function esEnfermero(): bool
    {
        return strtoupper($this->nombre) === self::ENFERMERO;
    }

    public function esSecretaria(): bool
    {
        return strtoupper($this->nombre) === self::SECRETARIA;
    }

    public function esAuxiliar(): bool
    {
        return strtoupper($this->nombre) === self::AUXILIAR;
    }

    // Métodos auxiliares adicionales
    public function puedeCrearCitas(): bool
    {
        return $this->esAdministrador() || $this->esSecretaria();
    }

    public function puedeGestionarAgenda(): bool
    {
        return $this->esAdministrador() || $this->esMedico() || $this->esEnfermero();
    }

    public function puedeGestionarUsuarios(): bool
    {
        return $this->esAdministrador();
    }

    public function puedeVerReportes(): bool
    {
        return $this->esAdministrador() || $this->esMedico();
    }

    public function requiereEspecialidad(): bool
    {
        return $this->esMedico();
    }

    public function requiereRegistroProfesional(): bool
    {
        return $this->esMedico() || $this->esEnfermero();
    }

    // Método para obtener permisos del rol
    public function getPermisosAttribute(): array
    {
        return [
            'puede_crear_citas' => $this->puedeCrearCitas(),
            'puede_gestionar_agenda' => $this->puedeGestionarAgenda(),
            'puede_gestionar_usuarios' => $this->puedeGestionarUsuarios(),
            'puede_ver_reportes' => $this->puedeVerReportes(),
            'requiere_especialidad' => $this->requiereEspecialidad(),
            'requiere_registro_profesional' => $this->requiereRegistroProfesional(),
        ];
    }

    // Método para obtener descripción del rol
    public function getDescripcionRolAttribute(): string
    {
        $descripciones = [
            self::ADMINISTRADOR => 'Administrador del sistema con acceso completo',
            self::MEDICO => 'Médico con acceso a agenda y pacientes',
            self::ENFERMERO => 'Enfermero con acceso a agenda y apoyo médico',
            self::SECRETARIA => 'Secretaria con acceso a citas y recepción',
            self::AUXILIAR => 'Auxiliar con acceso limitado'
        ];

        return $descripciones[strtoupper($this->nombre)] ?? 'Rol no definido';
    }

    // Método para obtener el nivel de acceso (útil para jerarquías)
    public function getNivelAccesoAttribute(): int
    {
        $niveles = [
            self::ADMINISTRADOR => 5,
            self::MEDICO => 4,
            self::ENFERMERO => 3,
            self::SECRETARIA => 2,
            self::AUXILIAR => 1
        ];

        return $niveles[strtoupper($this->nombre)] ?? 0;
    }

    // Scopes existentes
    public function scopeByNombre($query, $nombre)
    {
        return $query->where('nombre', strtoupper($nombre));
    }

    // Scopes adicionales
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConUsuarios($query)
    {
        return $query->has('usuarios');
    }

    public function scopeSinUsuarios($query)
    {
        return $query->doesntHave('usuarios');
    }

    public function scopeAdministradores($query)
    {
        return $query->where('nombre', self::ADMINISTRADOR);
    }

    public function scopeMedicos($query)
    {
        return $query->where('nombre', self::MEDICO);
    }

    public function scopePersonalMedico($query)
    {
        return $query->whereIn('nombre', [self::MEDICO, self::ENFERMERO]);
    }

    public function scopePersonalAdministrativo($query)
    {
        return $query->whereIn('nombre', [self::SECRETARIA, self::AUXILIAR]);
    }

    // Método estático para obtener rol por nombre
    public static function obtenerPorNombre(string $nombre): ?self
    {
        return self::where('nombre', strtoupper($nombre))->first();
    }

    // Método estático para verificar si un rol existe
    public static function existeRol(string $nombre): bool
    {
        return self::where('nombre', strtoupper($nombre))->exists();
    }

    // Método para contar usuarios por rol
    public function contarUsuarios(): int
    {
        return $this->usuarios()->count();
    }

    public function contarUsuariosActivos(): int
    {
        return $this->usuariosActivos()->count();
    }

    // Método toString
    public function __toString(): string
    {
        return $this->nombre;
    }

    // Método para validar si puede tener ciertos permisos
    public function puedeAsignarPermiso(string $permiso): bool
    {
        $permisosDisponibles = [
            self::ADMINISTRADOR => ['*'], // Todos los permisos
            self::MEDICO => ['agenda', 'pacientes', 'reportes', 'citas'],
            self::ENFERMERO => ['agenda', 'pacientes', 'citas'],
            self::SECRETARIA => ['citas', 'pacientes', 'recepcion'],
            self::AUXILIAR => ['basico']
        ];

        $permisos = $permisosDisponibles[strtoupper($this->nombre)] ?? [];
        
        return in_array('*', $permisos) || in_array($permiso, $permisos);
    }
}
