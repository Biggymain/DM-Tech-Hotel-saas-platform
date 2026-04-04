<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\LeisureAccessLog;
use App\Models\StaffDailyPin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LeisureService
{
    /**
     * Verify the "Triangle of Truth" for access control.
     * 1. Membership (Is there an active subscription?)
     * 2. Pass/Order (Was a Pool-Pass paid/served in the last 24h?)
     * 3. Reservation (Is the guest currently checked-in?)
     */
    public function verifyAccess(string $code, int $outletId): array
    {
        $user = User::where('id', $code)
            ->orWhere('email', $code)
            ->first();

        // If it's a staff daily PIN
        $staffPin = StaffDailyPin::where('daily_pin', $code)
            ->where('expires_at', '>', now())
            ->first();

        if ($staffPin) {
            $this->logAccess($staffPin->user_id, $outletId, 'PIN', $code, true);
            return ['allow' => true, 'type' => 'staff', 'user' => $staffPin->user];
        }

        // Find user by code (could be RFID or UUID or dynamic QR data)
        if (!$user) {
            // Check for temporary guest QR/Pass in orders
            $order = Order::where('order_number', $code)
                ->where('order_status', 'served')
                ->where('created_at', '>', now()->subDay())
                ->first();

            if ($order) {
                // Verify "Inventory Deduction" (Triangle of Truth: Level 2)
                // This is implicit if the order is 'served' as per our new Drink-Check rule
                $this->logAccess($order->created_by, $outletId, 'QR', $code, true);
                return ['allow' => true, 'type' => 'pass', 'order' => $order];
            }

            $this->logAccess(null, $outletId, 'UNKNOWN', $code, false);
            return ['allow' => false, 'message' => 'Invalid Access Code'];
        }

        // check Active Membership
        $membership = Membership::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($membership) {
            $this->logAccess($user->id, $outletId, 'MEMBERSHIP', $code, true);
            return ['allow' => true, 'type' => 'membership'];
        }

        // check Active Reservation
        $reservation = Reservation::where('guest_id', $user->id)
            ->where('status', 'checked_in')
            ->first();

        if ($reservation) {
            $this->logAccess($user->id, $outletId, 'RESERVATION', $code, true);
            return ['allow' => true, 'type' => 'reservation'];
        }

        $this->logAccess($user->id, $outletId, 'DENIED', $code, false);
        return ['allow' => false, 'message' => 'No active membership or pass'];
    }

    public function generateDailyPin(int $userId): string
    {
        $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        
        StaffDailyPin::updateOrCreate(
            ['user_id' => $userId],
            [
                'daily_pin' => $pin,
                'expires_at' => now()->addHours(12),
            ]
        );

        return $pin;
    }

    private function logAccess(?int $userId, int $outletId, string $method, string $code, bool $allow)
    {
        LeisureAccessLog::create([
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'method' => $method,
            'code' => $code,
            'allow' => $allow,
            'entry_time' => now(),
        ]);
    }
}
