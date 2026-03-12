<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $notifications = Notification::where('hotel_id', $user->hotel_id)
            ->where(function($q) use ($user) {
                // Must belong to the user OR be a broadcast they accessed
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $success = $this->notificationService->markAsRead($id, $user->id);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found or access denied.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read.'
        ]);
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $count = $this->notificationService->markAllAsRead($user->hotel_id, $user->id);

        return response()->json([
            'status' => 'success',
            'message' => "$count notifications marked as read."
        ]);
    }

    /**
     * Remove the specified notification from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $notification = Notification::where('hotel_id', $user->hotel_id)
            ->where('id', $id)
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found or access denied.'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted.'
        ]);
    }
}
