<?php
// app/Services/SyncService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SyncQueue;
use Carbon\Carbon;

class SyncService
{
    protected $syncableTables = [
        'pacientes', 'citas', 'agendas', 'historias_clinicas',
        'historias_clinicas_complementarias', 'hcs_paraclinicos',
        'hcs_visitas', 'historia_diagnosticos', 'historia_medicamentos',
        'historia_remisiones', 'historia_cups', 'facturas'
    ];

    protected $masterTables = [
        'departamentos', 'municipios', 'empresas', 'regimenes',
        'tipos_afiliacion', 'zonas_residenciales', 'razas',
        'escolaridades', 'tipos_parentesco', 'tipos_documento',
        'ocupaciones', 'novedades', 'auxiliares', 'brigadas',
        'estados', 'roles', 'especialidades', 'procesos',
        'contratos', 'cups', 'categorias_cups', 'cups_contratados',
        'diagnosticos', 'medicamentos', 'remisiones'
    ];

    public function getSyncableTables(): array
    {
        return $this->syncableTables;
    }

    public function getMasterTables(): array
    {
        return $this->masterTables;
    }

    /**
     * Obtener cambios para una sede específica
     */
    public function getChangesForSede(int $sedeId, ?Carbon $lastSync = null, array $tables = [], int $limit = 100): array
    {
        $query = SyncQueue::where('sede_id', '!=', $sedeId)
            ->where('status', 'SYNCED');

        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        if (!empty($tables)) {
            $query->whereIn('table_name', $tables);
        }

        $changes = $query->orderBy('updated_at')
            ->limit($limit)
            ->get();

        return $changes->map(function ($change) {
            return [
                'table_name' => $change->table_name,
                'record_uuid' => $change->record_uuid,
                'operation' => $change->operation,
                'data' => $change->data,
                'created_at_offline' => $change->created_at_offline,
                'updated_at' => $change->updated_at
            ];
        })->toArray();
    }

    /**
     * Procesar un cambio entrante
     */
    public function processIncomingChange(
        int $sedeId,
        string $tableName,
        string $recordUuid,
        string $operation,
        ?array $data = null,
        Carbon $createdAtOffline
    ): array {
        try {
            // Verificar si ya existe el registro
            $existingRecord = $this->findRecordByUuid($tableName, $recordUuid);
            
            switch ($operation) {
                case 'CREATE':
                    return $this->processCreate($tableName, $recordUuid, $data, $existingRecord);
                    
                case 'UPDATE':
                    return $this->processUpdate($tableName, $recordUuid, $data, $existingRecord, $createdAtOffline);
                    
                case 'DELETE':
                    return $this->processDelete($tableName, $recordUuid, $existingRecord);
                    
                default:
                    throw new \InvalidArgumentException("Operación no válida: {$operation}");
            }

        } catch (\Exception $e) {
            Log::error("Error procesando cambio: {$e->getMessage()}", [
                'table_name' => $tableName,
                'record_uuid' => $recordUuid,
                'operation' => $operation
            ]);

            return [
                'status' => 'FAILED',
                'message' => $e->getMessage()
            ];
        }
    }

    protected function processCreate(string $tableName, string $recordUuid, array $data, $existingRecord): array
    {
        if ($existingRecord) {
            return [
                'status' => 'CONFLICT',
                'message' => 'El registro ya existe',
                'conflicts' => ['uuid' => $recordUuid]
            ];
        }

        // Asegurar que el UUID esté en los datos
        $data['uuid'] = $recordUuid;
        
        // Crear el registro
        DB::table($tableName)->insert($data);

        return [
            'status' => 'SUCCESS',
            'message' => 'Registro creado exitosamente'
        ];
    }

    protected function processUpdate(string $tableName, string $recordUuid, array $data, $existingRecord, Carbon $createdAtOffline): array
    {
        if (!$existingRecord) {
            // Si no existe, lo creamos
            $data['uuid'] = $recordUuid;
            DB::table($tableName)->insert($data);
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Registro creado (no existía)'
            ];
        }

        // Verificar conflictos de timestamp
        if (isset($existingRecord->updated_at)) {
            $existingUpdatedAt = Carbon::parse($existingRecord->updated_at);
            if ($existingUpdatedAt->gt($createdAtOffline)) {
                return [
                    'status' => 'CONFLICT',
                    'message' => 'El registro local es más reciente',
                    'conflicts' => [
                        'local_updated_at' => $existingUpdatedAt->toISOString(),
                        'incoming_updated_at' => $createdAtOffline->toISOString()
                    ]
                ];
            }
        }

        // Actualizar el registro
        DB::table($tableName)
            ->where('uuid', $recordUuid)
            ->update($data);

        return [
            'status' => 'SUCCESS',
            'message' => 'Registro actualizado exitosamente'
        ];
    }

    protected function processDelete(string $tableName, string $recordUuid, $existingRecord): array
    {
        if (!$existingRecord) {
            return [
                'status' => 'SUCCESS',
                'message' => 'Registro ya no existe'
            ];
        }

        // Soft delete si la tabla lo soporta
        if ($this->hasSoftDeletes($tableName)) {
            DB::table($tableName)
                ->where('uuid', $recordUuid)
                ->update(['deleted_at' => now()]);
        } else {
            DB::table($tableName)
                ->where('uuid', $recordUuid)
                ->delete();
        }

        return [
            'status' => 'SUCCESS',
            'message' => 'Registro eliminado exitosamente'
        ];
    }

    protected function findRecordByUuid(string $tableName, string $recordUuid)
    {
        $query = DB::table($tableName)->where('uuid', $recordUuid);
        
        // Si tiene soft deletes, incluir registros eliminados
        if ($this->hasSoftDeletes($tableName)) {
            $query->whereNull('deleted_at');
        }
        
        return $query->first();
    }

    protected function hasSoftDeletes(string $tableName): bool
    {
        return DB::getSchemaBuilder()->hasColumn($tableName, 'deleted_at');
    }

    /**
     * Obtener estado de las tablas para una sede
     */
    public function getTablesStatus(int $sedeId): array
    {
        $status = [];
        
        foreach ($this->syncableTables as $table) {
            $status[$table] = [
                'pending' => SyncQueue::bySede($sedeId)
                    ->byTable($table)
                    ->pending()
                    ->count(),
                'failed' => SyncQueue::bySede($sedeId)
                    ->byTable($table)
                    ->where('status', 'FAILED')
                    ->count(),
                'last_sync' => SyncQueue::bySede($sedeId)
                    ->byTable($table)
                    ->where('status', 'SYNCED')
                    ->latest('updated_at')
                    ->value('updated_at')
            ];
        }

        return $status;
    }

    /**
     * Realizar sincronización completa
     */
    public function performFullSync(int $sedeId, array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            try {
                // Obtener todos los registros de la tabla para otras sedes
                $records = DB::table($table)
                    ->where('sede_id', '!=', $sedeId)
                    ->orWhereIn('id', function ($query) use ($table) {
                        // Incluir tablas maestras (sin sede_id)
                        if (in_array($table, $this->masterTables)) {
                            $query->select('id')->from($table);
                        }
                    })
                    ->get();

                $syncCount = 0;
                foreach ($records as $record) {
                    $recordArray = (array) $record;
                    
                    // Procesar cada registro
                    $result = $this->processIncomingChange(
                        $sedeId,
                        $table,
                        $record->uuid,
                        'CREATE',
                        $recordArray,
                        now()
                    );

                    if ($result['status'] === 'SUCCESS') {
                        $syncCount++;
                    }
                }

                $results[$table] = [
                    'total_records' => $records->count(),
                    'synced_records' => $syncCount,
                    'status' => 'SUCCESS'
                ];

            } catch (\Exception $e) {
                $results[$table] = [
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
