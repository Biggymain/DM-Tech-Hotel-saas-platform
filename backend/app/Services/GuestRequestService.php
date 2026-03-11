<?php

namespace App\Services;

use App\Models\GuestServiceRequest;
use App\Models\GuestPortalSession;
use Illuminate\Validation\ValidationException;
use App\Models\Hotel;

class GuestRequestService
{
    protected $housekeepingService;
    protected $maintenanceService;

    public function __construct(HousekeepingService $housekeepingService, MaintenanceService $maintenanceService)
    {
        $this->housekeepingService = $housekeepingService;
        $this->maintenanceService = $maintenanceService;
    }

    public function createRequest(GuestPortalSession $session, array $data)
    {
        if (!$session->is_active || $session->expires_at < now()) {
            throw ValidationException::withMessages(['session' => 'Session is expired or inactive.']);
        }

        $request = GuestServiceRequest::create([
            'hotel_id' => $session->hotel_id,
            'guest_id' => $session->guest_id,
            'room_id' => $session->room_id,
            'reservation_id' => $session->reservation_id,
            'request_type' => $data['request_type'],
            'description' => $data['description'] ?? null,
            'status' => 'pending'
        ]);

        // Route internally based on request_type
        if ($data['request_type'] === 'housekeeping') {
            \App\Models\HousekeepingTask::create([
                'hotel_id' => $session->hotel_id,
                'room_id' => $session->room_id,
                'task_type' => 'cleaning',
                'status' => 'pending',
                'notes' => $data['description'] ?? 'Guest reported via portal'
            ]);
        } elseif ($data['request_type'] === 'maintenance') {
            $this->maintenanceService->createMaintenanceRequest([
                'room_id' => $session->room_id,
                'issue_type' => 'other',
                'priority' => 'medium',
                'description' => $data['description'] ?? 'Guest reported an issue via portal',
            ], $session->hotel_id);
        } elseif ($data['request_type'] === 'late_checkout') {
            // Add 2 hours grace period optionally depending on configuration
            if ($session->reservation) {
                // Notifying reception logic and appending note to reservation optionally
            }
        }

        // Fire event
        if (class_exists(\App\Events\GuestServiceRequested::class)) {
            event(new \App\Events\GuestServiceRequested($request));
        }

        return $request;
    }
}
