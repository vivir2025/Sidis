<?php
// app/Console/Commands/SyncCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncService;
use Illuminate\Support\Facades\Log;

class SyncCommand extends Command
{
    protected $signature = 'sync:run {--sede-id=} {--full} {--tables=*}';
    protected $description = 'Ejecutar sincronización de datos';

    public function handle(SyncService $syncService)
    {
        $sedeId = $this->option('sede-id') ?? config('app.sede_id');
        $full = $this->option('full');
        $tables = $this->option('tables');

        if (!$sedeId) {
            $this->error('Debe especificar el ID de la sede');
            return 1;
        }

        $this->info("Iniciando sincronización para sede: {$sedeId}");

        try {
            if ($full) {
                $this->info('Ejecutando sincronización completa...');
                $results = $syncService->performFullSync($sedeId, $tables ?: []);
                
                foreach ($results as $table => $result) {
                    if ($result['status'] === 'SUCCESS') {
                        $this->info("✓ {$table}: {$result['synced_records']}/{$result['total_records']} registros");
                    } else {
                        $this->error("✗ {$table}: {$result['error']}");
                    }
                }
            } else {
                $this->info('Ejecutando sincronización incremental...');
                // Lógica de sincronización incremental
                $changes = $syncService->getChangesForSede($sedeId, now()->subHours(1));
                $this->info("Procesados " . count($changes) . " cambios");
            }

            $this->info('Sincronización completada exitosamente');
            return 0;

        } catch (\Exception $e) {
            $this->error("Error en sincronización: {$e->getMessage()}");
            Log::error('Error en comando de sincronización', [
                'sede_id' => $sedeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
