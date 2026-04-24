<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemLogController extends Controller
{
    /**
     * Retrieve high-severity SIEM alerts for the Master Dashboard.
     */
    public function index(Request $request)
    {
        // Query AuditLog for high-severity security events
        // In this architecture, SIEM alerts are filtered from AuditLogs with specific change types
        $alerts = AuditLog::with('user:id,name')
            ->whereIn('change_type', [
                'hardware_mismatch', 
                'port_violation', 
                'cross_tenant_violation', 
                'tamper_detected',
                'login_lockout'
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'message' => $log->reason,
                    'severity' => $this->getSeverityForType($log->change_type),
                    'source' => $log->source,
                    'user' => $log->user ? $log->user->name : 'Unknown',
                    'user_id' => $log->user_id,
                    'hardware_id' => $log->hardware_id ?? 'Unknown',
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $alerts,
            'total' => $alerts->count()
        ]);
    }

    private function getSeverityForType(string $type): int
    {
        return match($type) {
            'tamper_detected' => 15,
            'cross_tenant_violation' => 14,
            'hardware_mismatch', 'port_violation' => 12,
            default => 8,
        };
    }
    /**
     * Ban a hardware ID in the Supabase Licensing Hub.
     */
    public function banHardware(Request $request)
    {
        $request->validate(['hardware_id' => 'required|string']);
        
        $hardwareId = $request->hardware_id;

        // Active Response: Block the device in Supabase
        DB::connection('supabase')->table('devices')
            ->where('hardware_hash', $hardwareId)
            ->update(['is_manually_locked' => true]);

        Log::alert("SIEM ACTIVE RESPONSE: Hardware ID {$hardwareId} has been manually locked by " . auth()->user()->email);

        return response()->json(['message' => "Hardware ID {$hardwareId} successfully locked."]);
    }

    public function activityLogs(Request $request)
    {
        $query = ActivityLog::with(['user:id,name', 'outlet:id,name'])
            ->where('hotel_id', request()->user()->hotel_id)
            ->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->has('date_range')) {
            $dates = explode(',', $request->date_range);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', [$dates[0], $dates[1] . ' 23:59:59']);
            }
        }

        return response()->json($query->paginate(50));
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with(['user:id,name'])
            ->where('hotel_id', request()->user()->hotel_id)
            ->latest();

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->has('change_type')) {
            $query->where('change_type', $request->change_type);
        }
        if ($request->has('date_range')) {
            $dates = explode(',', $request->date_range);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', [$dates[0], $dates[1] . ' 23:59:59']);
            }
        }

        return response()->json($query->paginate(50));
    }
}
