<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\AuditLog;

class SystemLogController extends Controller
{
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
