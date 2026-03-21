<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class SendLoginAlert
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $time = now()->toDateTimeString();

        // 1. Send Email (Simulated)
        Log::info("Login Alert Sent to {$user->email}: IP: {$ip}, Device: {$userAgent}, Time: {$time}");

        // 2. Log to Audit Table (Requirement)
        AuditLog::create([
            'hotel_id' => $user->hotel_id,
            'user_id' => $user->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'change_type' => 'login',
            'reason' => 'Successful user login',
            'source' => 'api',
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);
    }
}
