<?php

namespace App\Services;

use App\Models\Room;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Events\MaintenanceRequested;
use App\Events\MaintenanceResolved;

class MaintenanceService
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function createMaintenanceRequest(array $data, int $hotelId, ?int $reportedBy = null): MaintenanceRequest
    {
        $request = MaintenanceRequest::create([
            'hotel_id' => $hotelId,
            'room_id' => $data['room_id'],
            'reported_by' => $reportedBy ?? auth()->id(),
            'issue_type' => $data['issue_type'],
            'priority' => $data['priority'] ?? 'low',
            'description' => $data['description'],
            'status' => 'open'
        ]);

        event(new MaintenanceRequested($request));

        return $request;
    }

    public function assignMaintenanceStaff(MaintenanceRequest $request, User $user, int $hotelId): MaintenanceRequest
    {
        $request->update([
            'assigned_to' => $user->id,
            'status' => 'assigned'
        ]);

        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'MaintenanceRequest',
            entityId: $request->id,
            changeType: 'Assigned',
            oldValues: [],
            newValues: ['assigned_to' => $user->id],
            userId: auth()->id(),
            reason: 'Maintenance request assigned',
            source: 'maintenance_module'
        );

        return $request;
    }

    public function markMaintenanceInProgress(MaintenanceRequest $request, int $hotelId, ?string $maintenanceUntil = null): MaintenanceRequest
    {
        $request->update(['status' => 'in_progress']);

        $room = $request->room;
        $oldStatus = $room->status;
        
        $roomUpdateData = ['status' => 'maintenance'];
        $auditNewValues = ['status' => 'maintenance'];

        if ($maintenanceUntil) {
            $roomUpdateData['maintenance_until'] = $maintenanceUntil;
            $auditNewValues['maintenance_until'] = $maintenanceUntil;
        }

        $room->update($roomUpdateData);

        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'Room',
            entityId: $room->id,
            changeType: 'Status Update',
            oldValues: ['status' => $oldStatus],
            newValues: $auditNewValues,
            userId: auth()->id() ?? $request->assigned_to,
            reason: 'Maintenance started',
            source: 'maintenance_module'
        );

        return $request;
    }

    public function resolveMaintenanceRequest(MaintenanceRequest $request, int $hotelId): MaintenanceRequest
    {
        $request->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $room = $request->room;
        $oldStatus = $room->status;
        $oldHkStatus = $room->housekeeping_status;

        $room->update([
            'status' => 'available',
            'housekeeping_status' => 'dirty',
            'maintenance_until' => null,
            'maintenance_notes' => null
        ]);

        event(new MaintenanceResolved($request));

        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'Room',
            entityId: $room->id,
            changeType: 'Status & Housekeeping Update',
            oldValues: [
                'status' => $oldStatus, 
                'housekeeping_status' => $oldHkStatus, 
                'maintenance_until' => $room->maintenance_until
            ],
            newValues: [
                'status' => 'available', 
                'housekeeping_status' => 'dirty',
                'maintenance_until' => null
            ],
            userId: auth()->id(),
            reason: 'Maintenance resolved',
            source: 'maintenance_module'
        );

        return $request;
    }
}
