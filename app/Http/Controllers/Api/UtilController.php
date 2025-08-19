<?php
// app/Http/Controllers/Api/UtilController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Storage, Log, Artisan};
use Illuminate\Support\Str;

class UtilController extends Controller
{
    public function createBackup(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupName = "backup_sede_{$user->sede_id}_{$timestamp}.sql";
            
            // Crear backup de la base de datos
            $databaseName = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            
            $backupPath = storage_path("app/backups/{$backupName}");
            
            // Crear directorio si no existe
            if (!file_exists(dirname($backupPath))) {
                mkdir(dirname($backupPath), 0755, true);
            }
            
            // Comando mysqldump
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($databaseName),
                escapeshellarg($backupPath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupPath)) {
                $fileSize = filesize($backupPath);
                
                Log::info("Backup creado exitosamente", [
                    'user_id' => $user->id,
                    'sede_id' => $user->sede_id,
                    'backup_file' => $backupName,
                    'file_size' => $fileSize
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'backup_name' => $backupName,
                        'file_size' => $this->formatBytes($fileSize),
                        'created_at' => now()->toISOString()
                    ],
                    'message' => 'Backup creado exitosamente'
                ]);
            } else {
                throw new \Exception('Error al crear el backup');
            }
            
        } catch (\Exception $e) {
            Log::error("Error al crear backup", [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el backup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function systemInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Información del sistema
            $systemInfo = [
                'server' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'operating_system' => PHP_OS,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'database' => [
                    'connection' => config('database.default'),
                    'driver' => config('database.connections.' . config('database.default') . '.driver'),
                    'host' => config('database.connections.' . config('database.default') . '.host'),
                    'port' => config('database.connections.' . config('database.default') . '.port'),
                    'database' => config('database.connections.' . config('database.default') . '.database'),
                ],
                'storage' => [
                    'disk_space' => $this->getDiskSpace(),
                    'storage_path' => storage_path(),
                    'public_path' => public_path(),
                ],
                'application' => [
                    'name' => config('app.name'),
                    'environment' => config('app.env'),
                    'debug' => config('app.debug'),
                    'url' => config('app.url'),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                ],
                'cache' => [
                    'default' => config('cache.default'),
                    'stores' => array_keys(config('cache.stores')),
                ],
                'session' => [
                    'driver' => config('session.driver'),
                    'lifetime' => config('session.lifetime'),
                ],
                'user_info' => [
                    'sede_id' => $user->sede_id,
                    'user_id' => $user->id,
                    'username' => $user->username ?? $user->email,
                ]
            ];

            // Estadísticas de la base de datos
            $dbStats = [
                'total_pacientes' => DB::table('pacientes')->where('sede_id', $user->sede_id)->count(),
                'total_citas' => DB::table('citas')->where('sede_id', $user->sede_id)->count(),
                'total_historias' => DB::table('historias_clinicas')->where('sede_id', $user->sede_id)->count(),
                'total_facturas' => DB::table('facturas')->where('sede_id', $user->sede_id)->count(),
                'citas_hoy' => DB::table('citas')
                    ->where('sede_id', $user->sede_id)
                    ->whereDate('fecha', now())
                    ->count(),
                'historias_mes_actual' => DB::table('historias_clinicas')
                    ->where('sede_id', $user->sede_id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'system_info' => $systemInfo,
                    'database_stats' => $dbStats,
                    'timestamp' => now()->toISOString()
                ],
                'message' => 'Información del sistema obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al obtener información del sistema", [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del sistema: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testConnection(Request $request): JsonResponse
    {
        try {
            $tests = [];
            
            // Test de conexión a la base de datos
            try {
                DB::connection()->getPdo();
                $tests['database'] = [
                    'status' => 'success',
                    'message' => 'Conexión a la base de datos exitosa',
                    'response_time' => $this->measureExecutionTime(function() {
                        return DB::select('SELECT 1');
                    })
                ];
            } catch (\Exception $e) {
                $tests['database'] = [
                    'status' => 'error',
                    'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
                ];
            }

            // Test de escritura en storage
            try {
                $testFile = 'test_' . Str::random(10) . '.txt';
                Storage::put($testFile, 'test content');
                $canRead = Storage::exists($testFile);
                Storage::delete($testFile);
                
                $tests['storage'] = [
                    'status' => $canRead ? 'success' : 'error',
                    'message' => $canRead ? 'Storage funcionando correctamente' : 'Error en el storage'
                ];
            } catch (\Exception $e) {
                $tests['storage'] = [
                    'status' => 'error',
                    'message' => 'Error en storage: ' . $e->getMessage()
                ];
            }

            // Test de cache
            try {
                $cacheKey = 'test_' . Str::random(10);
                $cacheValue = 'test_value';
                
                cache()->put($cacheKey, $cacheValue, 60);
                $retrieved = cache()->get($cacheKey);
                cache()->forget($cacheKey);
                
                $tests['cache'] = [
                    'status' => ($retrieved === $cacheValue) ? 'success' : 'error',
                    'message' => ($retrieved === $cacheValue) ? 'Cache funcionando correctamente' : 'Error en el cache'
                ];
            } catch (\Exception $e) {
                $tests['cache'] = [
                    'status' => 'error',
                    'message' => 'Error en cache: ' . $e->getMessage()
                ];
            }

            // Test de logs
            try {
                Log::info('Test de conexión ejecutado', ['user_id' => $request->user()->id]);
                $tests['logs'] = [
                    'status' => 'success',
                    'message' => 'Sistema de logs funcionando correctamente'
                ];
            } catch (\Exception $e) {
                $tests['logs'] = [
                    'status' => 'error',
                    'message' => 'Error en logs: ' . $e->getMessage()
                ];
            }

            $overallStatus = collect($tests)->every(function($test) {
                return $test['status'] === 'success';
            }) ? 'success' : 'error';

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_status' => $overallStatus,
                    'tests' => $tests,
                    'timestamp' => now()->toISOString()
                ],
                'message' => 'Tests de conexión completados'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar tests de conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLogs(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lines' => 'sometimes|integer|min:1|max:1000',
                'level' => 'sometimes|in:emergency,alert,critical,error,warning,notice,info,debug',
                'date' => 'sometimes|date_format:Y-m-d'
            ]);

            $lines = $validated['lines'] ?? 100;
            $level = $validated['level'] ?? null;
            $date = $validated['date'] ?? now()->format('Y-m-d');

            // Ruta del archivo de log
            $logFile = storage_path("logs/laravel-{$date}.log");

            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => false,
                    'message' => "No se encontró el archivo de log para la fecha: {$date}"
                ], 404);
            }

            // Leer las últimas líneas del archivo
            $logContent = $this->tailFile($logFile, $lines);
            
            // Parsear los logs
            $logs = $this->parseLogContent($logContent, $level);

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'total_lines' => count($logs),
                    'file_path' => $logFile,
                    'file_size' => $this->formatBytes(filesize($logFile)),
                    'date' => $date,
                    'level_filter' => $level
                ],
                'message' => 'Logs obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener logs: ' . $e->getMessage()
            ], 500);
        }
    }

    // Métodos auxiliares privados
    private function formatBytes($size, $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    private function getDiskSpace(): array
    {
        $path = storage_path();
        return [
            'free' => $this->formatBytes(disk_free_space($path)),
            'total' => $this->formatBytes(disk_total_space($path)),
            'used' => $this->formatBytes(disk_total_space($path) - disk_free_space($path))
        ];
    }

    private function measureExecutionTime(callable $callback): string
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);
        return round(($end - $start) * 1000, 2) . ' ms';
    }

    private function tailFile(string $filepath, int $lines): string
    {
        $handle = fopen($filepath, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);
        return implode("", array_reverse($text));
    }

    private function parseLogContent(string $content, ?string $levelFilter = null): array
    {
        $lines = explode("\n", $content);
        $logs = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Patrón básico para logs de Laravel
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/', $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtolower($matches[2]);
                $message = $matches[3];

                // Filtrar por level si se especifica
                if ($levelFilter && $level !== strtolower($levelFilter)) {
                    continue;
                }

                $logs[] = [
                    'timestamp' => $timestamp,
                    'level' => $level,
                    'message' => $message,
                    'raw' => $line
                ];
            } else {
                // Si no coincide con el patrón, agregar como línea raw
                $logs[] = [
                    'timestamp' => null,
                    'level' => 'unknown',
                    'message' => $line,
                    'raw' => $line
                ];
            }
        }

        return array_reverse($logs); // Mostrar los más recientes primero
    }
}
