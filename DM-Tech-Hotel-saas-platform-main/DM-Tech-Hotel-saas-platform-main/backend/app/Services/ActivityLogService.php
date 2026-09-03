<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    /**
     * Log a user or system action.
     * 
     * @param int $hotelId
     * @param string $action
     * @param string $description
     * @param string|null $modelType
     * @param int|null $modelId
     * @param int|null $userId
     * @param int|null $outletId
     * @param string $severity ('info', 'warning', 'critical')
     * @param array $metadata
     * @param array $requestData (optional standard Request ip & agent)
     * @return ActivityLog
     */
    public function logAction(
        int $hotelId,
        string $action,
        string $description = '',
        ?string $modelType = null,
        ?int $modelId = null,
        ?int $userId = null,
        ?int $outletId = null,
        string $severity = 'info',
        array $metadata = [],
        array $requestData = []
    ): ActivityLog {
        return ActivityLog::create([
            'hotel_id' => $hotelId,
            'outlet_id' => $outletId,
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'metadata' => $metadata,
            'severity' => in_array($severity, ['info', 'warning', 'critical']) ? $severity : 'info',
            'ip_address' => $requestData['ip'] ?? request()->ip(),
            'device' => $requestData['user_agent'] ?? request()->userAgent(),
        ]);
    }

    /**
     * Log an automated system event where no user is present.
     */
    public function logSystemEvent(
        int $hotelId, 
        string $action, 
        string $description = '', 
        string $severity = 'info',
        array $metadata = []
    ): ActivityLog {
        return static::logAction(
            hotelId: $hotelId,
            action: $action,
            description: $description,
            userId: null,
            outletId: null,
            severity: $severity,
            metadata: $metadata
        );
    }
}
