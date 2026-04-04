<?php

namespace App\Services;

use App\Models\Room;
use App\Models\HousekeepingTask;
use App\Models\User;
use App\Events\HousekeepingTaskCreated;
use App\Events\RoomCleaned;
use Carbon\Carbon;

class HousekeepingService
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function assignCleaningTask(HousekeepingTask $task, User $user, int $hotelId): HousekeepingTask
    {
        $task->update([
            'assigned_to' => $user->id,
            'status' => 'pending'
        ]);

        $task->room->update([
            'assigned_housekeeper_id' => $user->id
        ]);
        
        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'HousekeepingTask',
            entityId: $task->id,
            changeType: 'Assigned',
            oldValues: [],
            newValues: ['assigned_to' => $user->id],
            userId: auth()->id(),
            reason: 'Task assigned to housekeeper',
            source: 'housekeeping_module'
        );

        return $task;
    }

    public function startTask(HousekeepingTask $task, int $hotelId): HousekeepingTask
    {
        $task->update(['status' => 'in_progress']);

        $oldStatus = $task->room->housekeeping_status;
        $task->room->update(['housekeeping_status' => 'cleaning']);

        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'Room',
            entityId: $task->room_id,
            changeType: 'Housekeeping Status Update',
            oldValues: ['housekeeping_status' => $oldStatus],
            newValues: ['housekeeping_status' => 'cleaning'],
            userId: auth()->id() ?? $task->assigned_to,
            reason: 'Housekeeping task started',
            source: 'housekeeping_module'
        );

        return $task;
    }

    public function completeTask(HousekeepingTask $task, int $hotelId, ?string $notes = null): HousekeepingTask
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes ?? $task->notes
        ]);

        $newHousekeepingStatus = $task->task_type === 'inspection' ? 'inspected' : 'clean';

        $room = $task->room ?? Room::withoutGlobalScopes()->find($task->room_id);
        if ($room) {
            $this->updateRoomStatusAfterCleaning($room, $newHousekeepingStatus, $hotelId);
        }

        return $task;
    }

    public function updateRoomStatusAfterCleaning(Room $room, string $newHousekeepingStatus, int $hotelId): Room
    {
        $oldStatus = $room->housekeeping_status;
        
        $updateData = [
            'housekeeping_status' => $newHousekeepingStatus,
            'assigned_housekeeper_id' => null
        ];

        if ($newHousekeepingStatus === 'clean') {
            $updateData['last_cleaned_at'] = now();
        } elseif ($newHousekeepingStatus === 'inspected') {
            $updateData['last_inspected_at'] = now();
        }

        $room->update($updateData);

        if ($newHousekeepingStatus === 'clean' || $newHousekeepingStatus === 'inspected') {
            event(new RoomCleaned($room));
        }

        $this->auditLogService->recordChange(
            hotelId: $hotelId,
            entityType: 'Room',
            entityId: $room->id,
            changeType: 'Housekeeping Status Update',
            oldValues: ['housekeeping_status' => $oldStatus],
            newValues: ['housekeeping_status' => $newHousekeepingStatus],
            userId: auth()->id(),
            reason: 'Housekeeping task completed',
            source: 'housekeeping_module'
        );

        return $room;
    }
}
