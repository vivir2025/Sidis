<?php
// app/Console/Commands/BackupCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackupCommand extends Command
{
    protected $signature = 'backup:create {--compress}';
    protected $description = 'Crear backup de la base de datos';

    public function handle()
    {
        $this->info('Iniciando backup de la base de datos...');

        try {
            $filename = 'backup_' . now()->format('Y_m_d_H_i_s') . '.sql';
            $compress = $this->option('compress');

            if ($compress) {
                $filename .= '.gz';
            }

            // Crear backup según el driver de BD
            $connection = config('database.default');
            $config = config("database.connections.{$connection}");

            switch ($config['driver']) {
                case 'mysql':
                    $this->createMysqlBackup($config, $filename, $compress);
                    break;
                case 'sqlite':
                    $this->createSqliteBackup($config, $filename, $compress);
                    break;
                default:
                    throw new \Exception("Driver {$config['driver']} no soportado para backup");
            }

            $this->info("Backup creado: {$filename}");

            // Limpiar backups antiguos
            $this->cleanOldBackups();

            return 0;

        } catch (\Exception $e) {
            $this->error("Error creando backup: {$e->getMessage()}");
            return 1;
        }
    }

    private function createMysqlBackup(array $config, string $filename, bool $compress)
    {
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['database'])
        );

        if ($compress) {
            $command .= ' | gzip';
        }

        $command .= ' > ' . storage_path("app/backups/{$filename}");

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Error ejecutando mysqldump');
        }
    }

    private function createSqliteBackup(array $config, string $filename, bool $compress)
    {
        $source = $config['database'];
        $destination = storage_path("app/backups/{$filename}");

        if ($compress) {
            $command = "gzip -c {$source} > {$destination}";
        } else {
            $command = "cp {$source} {$destination}";
        }

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Error copiando archivo SQLite');
        }
    }

    private function cleanOldBackups()
    {
        $retentionDays = config('app.backup_retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        $files = Storage::disk('backups')->files();

        foreach ($files as $file) {
            $fileTime = Storage::disk('backups')->lastModified($file);
            
            if (Carbon::createFromTimestamp($fileTime)->lt($cutoffDate)) {
                Storage::disk('backups')->delete($file);
                $this->info("Backup antiguo eliminado: {$file}");
            }
        }
    }
}
