<?php

namespace App\Modules\AuditLogs\Services;

use App\Modules\AuditLogs\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Log a state mutation event.
     */
    public static function log(
        int $actorId,
        string $actorRole,
        string $actionType,
        string $entityType,
        int $entityId,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}
