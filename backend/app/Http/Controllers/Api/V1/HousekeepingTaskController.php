<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\HousekeepingTask;
use App\Models\User;
use App\Services\HousekeepingService;

class HousekeepingTaskController extends Controller
{
    protected HousekeepingService $housekeepingService;

    public function __construct(HousekeepingService $housekeepingService)
    {
        $this->housekeepingService = $housekeepingService;
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');
        $tasks = HousekeepingTask::with(['room', 'assignee'])->where('hotel_id', '=', $tenantId)->get();
        
        return response()->json(['data' => $tasks]);
    }

    public function assign(Request $request, HousekeepingTask $task)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($request->user_id);
        $tenantId = app('tenant_id');

        $this->housekeepingService->assignCleaningTask($task, $user, $tenantId);

        return response()->json(['message' => 'Task assigned successfully.', 'data' => $task->fresh()]);
    }

    public function start(Request $request, HousekeepingTask $task)
    {
        $tenantId = app('tenant_id');
        $this->housekeepingService->startTask($task, $tenantId);

        return response()->json(['message' => 'Task started successfully.', 'data' => $task->fresh()]);
    }

    public function complete(Request $request, HousekeepingTask $task)
    {
        $request->validate(['notes' => 'nullable|string']);
        $tenantId = app('tenant_id');

        $this->housekeepingService->completeTask($task, $tenantId, $request->notes);

        return response()->json(['message' => 'Task completed successfully.', 'data' => $task->fresh()]);
    }

    public function statusSummary(Request $request)
    {
        $tenantId = app('tenant_id') ?? $request->user()->hotel_id;
        
        $summary = [
            'pending' => HousekeepingTask::where('hotel_id', $tenantId)->where('status', 'pending')->count(),
            'in_progress' => HousekeepingTask::where('hotel_id', $tenantId)->where('status', 'in_progress')->count(),
            'completed' => HousekeepingTask::where('hotel_id', $tenantId)->where('status', 'completed')->count(),
        ];

        return response()->json($summary);
    }
}
