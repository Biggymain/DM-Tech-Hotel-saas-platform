<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log a sensitive change or action.
     */
    public static function log(
        string $entityType,
        $entityId,
        string $changeType,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        string $source = 'api'
    ) {
        $tenantId = null;
        if (app()->bound('tenant_id')) {
            $tenantId = app('tenant_id');
        } elseif (Auth::check()) {
            $tenantId = Auth::user()->hotel_id;
        }

        return AuditLog::create([
            'hotel_id' => $tenantId,
            'user_id' => Auth::id(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'change_type' => $changeType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'source' => $source,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
