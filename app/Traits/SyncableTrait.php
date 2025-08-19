<?php
// app/Traits/SyncableTrait.php
namespace App\Traits;

use App\Models\SyncQueue;
use Illuminate\Support\Facades\Auth;

trait SyncableTrait
{
    protected static function bootSyncableTrait()
    {
        static::created(function ($model) {
            $model->addToSyncQueue('CREATE');
        });

        static::updated(function ($model) {
            $model->addToSyncQueue('UPDATE');
        });

        static::deleted(function ($model) {
            $model->addToSyncQueue('DELETE');
        });
    }

    public function addToSyncQueue($operation)
    {
        if (!$this->shouldSync()) {
            return;
        }

        SyncQueue::create([
            'sede_id' => $this->sede_id ?? Auth::user()->sede_id ?? 1,
            'table_name' => $this->getTable(),
            'record_uuid' => $this->uuid,
            'record_id' => $this->id,
            'operation' => $operation,
            'data' => $operation === 'DELETE' ? null : $this->toArray(),
            'status' => 'PENDING',
            'created_at_offline' => now()
        ]);
    }

    protected function shouldSync(): bool
    {
        return !app()->runningInConsole() || 
               request()->header('X-Sync-Enabled') === 'true';
    }
}
