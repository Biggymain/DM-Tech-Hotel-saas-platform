<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Services\GuestRequestService;
use App\Models\GuestPortalSession;
use App\Models\GuestServiceRequest;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class GuestRequestController extends Controller
{
    use AuthorizesRequests;

    protected $requestService;

    public function __construct(GuestRequestService $requestService)
    {
        $this->requestService = $requestService;
    }

    public function index(Request $request)
    {
        // For guests filtering by session_token
        if ($request->has('session_token')) {
            $session = GuestPortalSession::where('session_token', $request->session_token)
                ->where('status', '!=', 'revoked')
                ->where('expires_at', '>', now())
                ->firstOrFail();

            $requests = GuestServiceRequest::where('room_id', $session->room_id)
                ->get();
                
            return response()->json($requests);
        }

        // For Staff (requires permission)
        // Handled by role.verify middleware in api.php
        return response()->json(GuestServiceRequest::with(['room', 'guest', 'assignedUser'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'request_type' => 'required|in:housekeeping,maintenance,concierge,late_checkout,other',
            'description' => 'nullable|string'
        ]);

        $session = GuestPortalSession::where('session_token', $request->session_token)
            ->where('status', '!=', 'revoked')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $serviceRequest = $this->requestService->createRequest($session, $request->all());

        return response()->json([
            'message' => 'Request created successfully.',
            'data' => $serviceRequest
        ], 201);
    }

    /**
     * Assign a service request to a staff member.
     */
    public function assign(Request $request, GuestServiceRequest $serviceRequest)
    {
        $request->validate([
            'assigned_user_id' => 'required|exists:users,id'
        ]);

        $serviceRequest->update([
            'assigned_user_id' => $request->assigned_user_id,
            'status' => 'assigned'
        ]);

        return response()->json([
            'message' => 'Request assigned successfully.',
            'data' => $serviceRequest->load('assignedUser')
        ]);
    }

    /**
     * Mark a service request as completed.
     */
    public function complete(Request $request, GuestServiceRequest $serviceRequest)
    {
        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        return response()->json([
            'message' => 'Request marked as completed.',
            'data' => $serviceRequest
        ]);
    }
}
