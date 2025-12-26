<!-- Instrucciones concisas para agentes de IA que trabajen en este repositorio -->
# Instrucciones para Copilot / Agentes de IA

Resumen rápido
- **Código base:** Aplicación Laravel 10 (PHP ^8.1). Entrada CLI: `artisan`; front entry: `public/index.php`.
- **Estructura relevante:** modelos y clases están en `app/` (no todos en `app/Models`), controladores en `app/Http/Controllers`, helpers en `app/Helpers`, providers en `app/Providers`, rutas en `routes/`.

Configuración y flujo de trabajo local (comandos clave)
- Instalar dependencias PHP: `composer install`.
- Copiar entorno: `cp .env.example .env` (Windows: copiar manualmente o `copy .env.example .env`).
- Generar clave: `php artisan key:generate`.
- Migraciones: `php artisan migrate`.
- Servidor dev: `php artisan serve` (útil en entornos sin XAMPP) o usar el host/virtualhost de XAMPP.
- Dependencias JS y Vite: `npm install` luego `npm run dev` (o `npm run build` para prod).
- Tests: `php artisan test` o `./vendor/bin/phpunit`.

Patrones y convenciones del proyecto (observables en el código)
- Autoload PSR-4 mapea `App\` → `app/` (ver `composer.json`).
- Muchos modelos están en la raíz de `app/` (ej: `app/Agenda.php`, `app/Paciente.php`) en vez de `app/Models/`. Buscar modelos allí primero.
- Hay pares de archivos con nombres en español/inglés (ej: `app/User.php` y `app/Usuario.php`, `app/Rol.php` y `app/Role.php`): antes de crear uno nuevo, verificar posibles duplicados semánticos.
- Lógica auxiliar reutilizable suele vivir en `app/Helpers/` (ej: `app/Helpers/SedeHelper.php`). Servicios específicos pueden estar en `app/Services/`.
- Resource classes en `app/Http/Resources/` y Requests en `app/Http/Requests/`.

Integraciones y dependencias externas relevantes
- Autenticación/API: se utiliza `laravel/sanctum` (ver `composer.json` y `config/sanctum.php`).
- HTTP requests: `guzzlehttp/guzzle` está instalado; buscar integraciones hacia servicios externos en `app/Services` o `app/Http/Controllers`.
- Debug/errores: `spatie/laravel-ignition` y `nunomaduro/collision` están en dev; la depuración espera `APP_DEBUG=true` en `.env`.
- Colas/background jobs: revisar `config/queue.php` y los modelos de job/listeners si se modifica procesamiento asíncrono.

Dónde buscar lógica de negocio y puntos de extensión
- Rutas y controladores: `routes/web.php`, `routes/api.php` y `app/Http/Controllers/`.
- Proveedores y bindings: `app/Providers/` (ej: `AppServiceProvider.php`) para bindings del contenedor y bootstrapping.
- Modelos y relaciones: `app/` (buscar modelos Eloquent, p. ej. `app/Cita.php`, `app/HistoriaClinica.php`).
- Migraciones y seeders: `database/migrations/`, `database/seeders/`.

Consejos para cambios de código y PRs
- Mantener PSR-4 y no mover modelos a `app/Models` sin actualizar `composer.json` o probar autoload.
- Evitar introducir duplicados de modelos en inglés/español: reusar o migrar cuidadosamente con pruebas.
- Para cambios que afectan datos, ejecutar `php artisan migrate` y proveer steps de rollback o seeders para pruebas.

Ejemplos concretos en el repo
- Revisar `routes/web.php` para entradas HTTP simples.
- Ver `app/Helpers/SedeHelper.php` para patrones de helper usados por controladores.
- Ver `app/Providers/AppServiceProvider.php` para cualquier binding/boot logic que altere comportamiento global.

Qué NO asumir
- No asumas que todos los modelos están en `app/Models` — este proyecto coloca la mayoría en `app/`.
- No asumas que existe una única fuente de roles/usuarios: hay `Role`/`Rol` y `User`/`Usuario` — investigar antes de modificar autorización.

Solicita aclaración cuando
- No estés seguro si una entidad nueva debe ser `Usuario` vs `User` o `Rol` vs `Role`.
- Los cambios requieren ajustar autoload o estructura de carpetas.

Contacto/feedback
- Después de añadir o modificar este archivo, por favor indícame si quieres que:
  - agregue ejemplos de PR (naming de ramas, tests mínimos),
  - documente alguna carpeta interna en más detalle.

---
Archivo creado/actualizado automáticamente por agente. Pide feedback si falta contexto específico.
