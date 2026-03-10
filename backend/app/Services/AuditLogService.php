<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * Record a standard entity change.
     */
    public function recordChange(
        int $hotelId,
        string $entityType,
        int $entityId,
        string $changeType,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
        ?string $reason = null,
        string $source = 'system'
    ): AuditLog {
        return AuditLog::create([
            'hotel_id' => $hotelId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'change_type' => $changeType,
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
            'reason' => $reason,
            'source' => in_array($source, ['system', 'api', 'manual', 'job']) ? $source : 'system',
        ]);
    }

    /**
     * Sugar wrapper for financial or stock adjustments marked unequivocally.
     */
    public function recordFinancialAction(
        int $hotelId,
        string $entityType,
        int $entityId,
        string $actionType, // refunded, discounted, stock_adjusted etc.
        array $adjustmentContext = [],
        ?int $userId = null,
        ?string $reason = null,
        string $source = 'system'
    ): AuditLog {
        return $this->recordChange(
            hotelId: $hotelId,
            entityType: $entityType,
            entityId: $entityId,
            changeType: $actionType,
            oldValues: $adjustmentContext['old'] ?? [],
            newValues: $adjustmentContext['new'] ?? [],
            userId: $userId,
            reason: $reason,
            source: $source
        );
    }
}
