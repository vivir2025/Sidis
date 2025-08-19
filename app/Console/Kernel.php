<?php
// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SyncCommand::class,
        Commands\BackupCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Sincronización cada 5 minutos si está habilitada
        if (config('app.sync_enabled', true)) {
            $schedule->command('sync:run')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/sync-cron.log'));
        }

        // Backup diario a las 2 AM
        if (config('app.backup_enabled', true)) {
            $schedule->command('backup:create --compress')
                ->dailyAt('02:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/backup-cron.log'));
        }

        // Limpiar logs antiguos semanalmente
        $schedule->command('log:clear')
            ->weekly()
            ->sundays()
            ->at('03:00');

        // Limpiar cola de sincronización mensualmente
        $schedule->call(function () {
            \App\Models\SyncQueue::where('status', 'SYNCED')
                ->where('updated_at', '<', now()->subDays(30))
                ->delete();
        })->monthly();

        // Optimizar base de datos semanalmente (SQLite)
        $schedule->call(function () {
            if (config('database.default') === 'sqlite') {
                \DB::statement('VACUUM');
                \DB::statement('ANALYZE');
            }
        })->weekly();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
