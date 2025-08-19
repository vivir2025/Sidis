<?php
// app/Models/SyncQueue.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncQueue extends Model
{
    protected $table = 'sync_queue';

    protected $fillable = [
        'sede_id', 'table_name', 'record_uuid', 'record_id',
        'operation', 'data', 'status', 'error_message', 'created_at_offline'
    ];

    protected $casts = [
        'data' => 'array',
        'created_at_offline' => 'datetime'
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopeByTable($query, $tableName)
    {
        return $query->where('table_name', $tableName);
    }
}
