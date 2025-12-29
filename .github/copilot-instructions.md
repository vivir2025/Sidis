# Instrucciones para Agentes de IA - SIDIS

## Contexto del Proyecto

**SIDIS** es un sistema de información de salud (health information system) construido con Laravel 10 + API REST. Es una aplicación multi-sede con sincronización offline, diseñada para gestionar pacientes, citas, historias clínicas, y datos maestros en múltiples ubicaciones geográficas.

## Arquitectura Central

### Sistema Multi-Sede con Sincronización
- **Modelo de datos distribuido**: Cada registro tiene un campo `uuid` (UUID global) y `sede_id` (ubicación)
- **Cola de sincronización**: Tabla `sync_queue` registra todos los cambios (CREATE/UPDATE/DELETE) con estados PENDING/SYNCED/FAILED
- **Trait SyncableTrait** ([app/Traits/SyncableTrait.php](app/Traits/SyncableTrait.php)): Automáticamente agrega cambios a la cola al crear/actualizar/eliminar modelos
- **SyncService** ([app/Services/SyncService.php](app/Services/SyncService.php)): Gestiona sincronización bidireccional entre sedes
- **Tablas sincronizables**: `pacientes`, `citas`, `agendas`, `historias_clinicas`, `facturas` (ver `$syncableTables` en SyncService)
- **Tablas maestras**: `departamentos`, `municipios`, `diagnosticos`, `medicamentos` (ver `$masterTables` en SyncService)

### Autenticación Multi-Sede
- Laravel Sanctum para tokens de API
- Usuario selecciona sede al hacer login ([AuthController::login](app/Http/Controllers/Api/AuthController.php))
- La sede activa se almacena en `session('sede_id')`
- Middleware `sede.access` verifica acceso a la sede seleccionada
- Usuarios pueden cambiar de sede con `POST /api/v1/auth/cambiar-sede`
- **SedeHelper** ([app/Helpers/SedeHelper.php](app/Helpers/SedeHelper.php)): Funciones auxiliares para obtener sede actual

## Patrones de Código Específicos

### Modelos con UUID
Todos los modelos sincronizables requieren:
```php
protected $fillable = ['uuid', 'sede_id', ...];

protected static function boot() {
    parent::boot();
    static::creating(function ($model) {
        if (empty($model->uuid)) {
            $model->uuid = Str::uuid();
        }
    });
}
```

### Controllers API
- **Namespace**: `App\Http\Controllers\Api`
- **Autenticación**: `$this->middleware('auth:sanctum')` en constructor
- **Formato de respuesta estándar**:
  ```php
  return response()->json([
      'success' => true,
      'data' => $resource,
      'message' => 'Operación exitosa'
  ]);
  ```
- **Filtros multi-sede**: Todos los index deben soportar `?sede_id={id}` opcional
- **Búsqueda general**: Parámetro `?search={term}` para búsqueda de texto
- **Paginación**: Parámetro `?per_page=15` (default), `?all=true` para sin paginación
- **Ordenamiento**: `?sort_by=campo&sort_order=asc|desc`

### Relaciones Comunes
- `Paciente`: hasMany `historias_clinicas`, `citas`
- `Cita`: belongsTo `paciente` (via `paciente_uuid`), `agenda`, `cups_contratado`
- `HistoriaClinica`: belongsTo `cita`, `sede`, hasMany `historiaDiagnosticos`, `historiaMedicamentos`, `historiaRemisiones`
- **Importante**: Relaciones entre sedes usan UUIDs, no IDs locales (ej: `citas.paciente_uuid` apunta a `pacientes.uuid`)

### Formato de Fechas
Los modelos con fechas usan casting específico:
```php
protected $casts = [
    'fecha_nacimiento' => 'date:Y-m-d',
    'fecha_registro' => 'date:Y-m-d',
];
```

## Comandos de Desarrollo

### Servidor Local (XAMPP)
```bash
# Laravel se ejecuta en XAMPP: http://localhost/Sidis/public
# No usar `php artisan serve` - configurado para Apache

# Compilar assets
npm run dev        # Modo desarrollo con watch
npm run build      # Compilación producción
```

### Base de Datos
```bash
php artisan migrate              # Ejecutar migraciones
php artisan db:seed              # Ejecutar seeders
php artisan migrate:fresh --seed # Reiniciar DB completa
```

### Testing
```bash
vendor/bin/phpunit               # Ejecutar todos los tests
vendor/bin/phpunit --filter=NombreTest
```

### Cache y Optimización
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize             # Optimizar para producción
```

## Rutas API Principales

- `POST /api/v1/auth/login` - Login con `{login, password, sede_id}`
- `GET /api/v1/auth/me` - Usuario autenticado
- `POST /api/v1/auth/cambiar-sede` - Cambiar sede activa
- `GET /api/v1/pacientes?search={term}&sede_id={id}` - Buscar pacientes
- `POST /api/v1/pacientes/verificacion/iniciar` - Verificación de identidad paso 1 (documento → nombre + 3 fechas)
- `POST /api/v1/pacientes/verificacion/validar` - Verificación de identidad paso 2 (validar fecha seleccionada)
- `GET /api/v1/historias-clinicas?documento={doc}&fecha_desde={date}` - Historias
- `GET /api/v1/sync/pull` - Obtener cambios para sincronizar
- `POST /api/v1/sync/push` - Enviar cambios locales

Todas las rutas protegidas requieren:
```
Authorization: Bearer {token}
```

### Sistema de Verificación de Identidad
Flujo en 2 pasos para validar identidad del paciente:
1. **Iniciar**: Enviar documento → Recibir nombre + 3 fechas de nacimiento (1 real, 2 falsas)
2. **Validar**: Enviar documento + fecha seleccionada → Confirmar si es correcta


## Convenciones de Código

### Logging
Usar Log facade con contexto descriptivo:
```php
Log::info('📋 API GET Request - Historias Clínicas', ['filters' => $request->all()]);
```

### Validaciones
Preferir Form Requests sobre validación inline:
```php
class StorePacienteRequest extends FormRequest {
    public function rules() { ... }
}
```

### Soft Deletes
Todos los modelos principales usan `SoftDeletes` - verificar `deleted_at` en queries

### Eager Loading
Siempre cargar relaciones necesarias para evitar N+1:
```php
$query->with(['sede', 'cita.paciente', 'cita.agenda.usuario']);
```

## Estructura de Archivos Importantes

- [app/Services/SyncService.php](app/Services/SyncService.php) - Lógica de sincronización
- [app/Traits/SyncableTrait.php](app/Traits/SyncableTrait.php) - Auto-registro en cola sync
- [app/Helpers/SedeHelper.php](app/Helpers/SedeHelper.php) - Utilidades de sede
- [app/Http/Middleware/SedeAccessMiddleware.php](app/Http/Middleware/SedeAccessMiddleware.php) - Verificación de acceso a sede
- [routes/api.php](routes/api.php) - Definición completa de endpoints
- [app/Models/](app/Models/) - 40+ modelos del dominio

## Consideraciones de Performance

1. **Optimización de login**: `SyncableTrait` detecta `request()->is('api/auth/login')` y omite sync para evitar latencia
2. **Queries con JOIN**: AuthController usa JOIN en lugar de eager loading para reducir queries
3. **Índices en sync_queue**: `['sede_id', 'status']` y `['record_uuid']` indexados
4. **Paginación por defecto**: Siempre paginar resultados grandes (per_page: 5-100)
