<?php
// app/Http/Controllers/Api/SyncController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SyncQueue;
use App\Services\SyncService;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Obtener cambios pendientes para sincronizar desde el servidor central
     */
    public function pullChanges(Request $request): JsonResponse
    {
        $request->validate([
            'last_sync' => 'nullable|date',
            'tables' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        $sedeId = $request->user()->sede_id;
        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : null;
        $tables = $request->tables ?? [];
        $limit = $request->limit ?? 100;

        try {
            $changes = $this->syncService->getChangesForSede(
                $sedeId, 
                $lastSync, 
                $tables, 
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'changes' => $changes,
                    'sync_timestamp' => now()->toISOString(),
                    'has_more' => count($changes) >= $limit
                ],
                'message' => 'Cambios obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en pullChanges: ' . $e->getMessage(), [
                'sede_id' => $sedeId,
                'last_sync' => $lastSync,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cambios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar cambios locales al servidor central
     */
    public function pushChanges(Request $request): JsonResponse
    {
        $request->validate([
            'changes' => 'required|array',
            'changes.*.table_name' => 'required|string',
            'changes.*.record_uuid' => 'required|string',
            'changes.*.operation' => 'required|in:CREATE,UPDATE,DELETE',
            'changes.*.data' => 'nullable|array',
            'changes.*.created_at_offline' => 'required|date'
        ]);

        $sedeId = $request->user()->sede_id;
        $changes = $request->changes;

        DB::beginTransaction();
        try {
            $results = [];
            
            foreach ($changes as $change) {
                $result = $this->syncService->processIncomingChange(
                    $sedeId,
                    $change['table_name'],
                    $change['record_uuid'],
                    $change['operation'],
                    $change['data'] ?? null,
                    Carbon::parse($change['created_at_offline'])
                );

                $results[] = [
                    'record_uuid' => $change['record_uuid'],
                    'table_name' => $change['table_name'],
                    'operation' => $change['operation'],
                    'status' => $result['status'],
                    'message' => $result['message'] ?? null,
                    'conflicts' => $result['conflicts'] ?? []
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'sync_timestamp' => now()->toISOString()
                ],
                'message' => 'Cambios procesados exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error en pushChanges: ' . $e->getMessage(), [
                'sede_id' => $sedeId,
                'changes_count' => count($changes),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar cambios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de la cola de sincronización
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $sedeId = $request->user()->sede_id;

        $status = [
            'pending_changes' => SyncQueue::bySede($sedeId)->pending()->count(),
            'failed_changes' => SyncQueue::bySede($sedeId)->where('status', 'FAILED')->count(),
            'last_sync' => SyncQueue::bySede($sedeId)
                ->where('status', 'SYNCED')
                ->latest('updated_at')
                ->value('updated_at'),
            'tables_status' => $this->syncService->getTablesStatus($sedeId)
        ];

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Reintentar cambios fallidos
     */
    public function retryFailedChanges(Request $request): JsonResponse
    {
        $sedeId = $request->user()->sede_id;

        $failedChanges = SyncQueue::bySede($sedeId)
            ->where('status', 'FAILED')
            ->get();

        $retryResults = [];
        
        foreach ($failedChanges as $change) {
            $change->update([
                'status' => 'PENDING',
                'error_message' => null
            ]);

            $retryResults[] = [
                'record_uuid' => $change->record_uuid,
                'table_name' => $change->table_name,
                'operation' => $change->operation
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'retried_changes' => $retryResults,
                'count' => count($retryResults)
            ],
            'message' => 'Cambios fallidos marcados para reintento'
        ]);
    }

    /**
     * Sincronización completa inicial
     */
    public function fullSync(Request $request): JsonResponse
    {
        $request->validate([
            'tables' => 'nullable|array'
        ]);

        $sedeId = $request->user()->sede_id;
        $tables = $request->tables ?? $this->syncService->getSyncableTables();

        try {
            $results = $this->syncService->performFullSync($sedeId, $tables);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Sincronización completa realizada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en fullSync: ' . $e->getMessage(), [
                'sede_id' => $sedeId,
                'tables' => $tables,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en sincronización completa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar registros de sincronización antiguos
     */
    public function cleanupSyncQueue(Request $request): JsonResponse
    {
        $request->validate([
            'days_old' => 'nullable|integer|min:1|max:365'
        ]);

        $sedeId = $request->user()->sede_id;
        $daysOld = $request->days_old ?? 30;

        $deleted = SyncQueue::bySede($sedeId)
            ->where('status', 'SYNCED')
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'deleted_records' => $deleted,
                'cleanup_date' => now()->subDays($daysOld)->toISOString()
            ],
            'message' => 'Limpieza de cola de sincronización completada'
        ]);
    }
}
