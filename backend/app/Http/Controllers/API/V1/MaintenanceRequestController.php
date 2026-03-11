<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceService;

class MaintenanceRequestController extends Controller
{
    protected MaintenanceService $maintenanceService;

    public function __construct(MaintenanceService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');
        $tasks = MaintenanceRequest::with(['room', 'reporter', 'assignee'])->where('hotel_id', '=', $tenantId)->get();
        
        return response()->json(['data' => $tasks]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'issue_type' => 'required|in:plumbing,electrical,furniture,hvac,other',
            'priority' => 'nullable|in:low,medium,high,critical',
            'description' => 'required|string'
        ]);

        $tenantId = app('tenant_id');
        $req = $this->maintenanceService->createMaintenanceRequest($validated, $tenantId, auth()->id());

        return response()->json(['message' => 'Maintenance request created successfully.', 'data' => $req], 201);
    }

    public function assign(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($request->user_id);
        $tenantId = app('tenant_id');

        $this->maintenanceService->assignMaintenanceStaff($maintenanceRequest, $user, $tenantId);

        return response()->json(['message' => 'Request assigned successfully.', 'data' => $maintenanceRequest->fresh()]);
    }

    public function start(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $request->validate(['maintenance_until' => 'nullable|date']);
        $tenantId = app('tenant_id');

        $this->maintenanceService->markMaintenanceInProgress($maintenanceRequest, $tenantId, $request->maintenance_until);

        return response()->json(['message' => 'Maintenance started successfully.', 'data' => $maintenanceRequest->fresh()]);
    }

    public function resolve(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $tenantId = app('tenant_id');
        $this->maintenanceService->resolveMaintenanceRequest($maintenanceRequest, $tenantId);

        return response()->json(['message' => 'Maintenance resolved successfully.', 'data' => $maintenanceRequest->fresh()]);
    }
}
