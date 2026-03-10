<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Create a targeted or global tenant notification
     */
    public function createNotification(array $data): Notification
    {
        $notification = Notification::create([
            'hotel_id' => $data['hotel_id'],
            'outlet_id' => $data['outlet_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'medium',
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? [],
            'expires_at' => $data['expires_at'] ?? Carbon::now()->addDays(30)
        ]);

        return $notification;
    }

    /**
     * Wrapper for limiting frequency per-type per-hotel using Redis
     */
    public function isRateLimited(int $hotelId, string $type, int $limitSeconds = 60): bool
    {
        $key = "throttle:notifications:{$hotelId}:{$type}";
        
        if (Redis::exists($key)) {
            return true;
        }

        Redis::setex($key, $limitSeconds, 1);
        return false;
    }

    /**
     * Mark an individual notification read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where(function($q) use ($userId) {
                // Must belong to the user OR be a broadcast they accessed
                $q->where('user_id', $userId)
                  ->orWhereNull('user_id');
            })
            ->first();

        if (!$notification) return false;

        $notification->update(['is_read' => true]);
        return true;
    }

    /**
     * Mark all notifications for a user/tenant as read
     */
    public function markAllAsRead(int $hotelId, int $userId): int
    {
        return Notification::where('hotel_id', $hotelId)
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Delete expired or old read notifications
     */
    public function clearExpired(): int
    {
        $now = Carbon::now();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return Notification::where('expires_at', '<', $now)
            ->orWhere(function ($query) use ($thirtyDaysAgo) {
                $query->where('is_read', true)
                      ->where('created_at', '<', $thirtyDaysAgo);
            })
            ->delete();
    }
}
