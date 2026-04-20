<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HotelSubscription;

class CheckSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-status';
    protected $description = 'Checks subscription status for T-3/T-1 alerts and auto-suspends accounts past 24h grace';

    public function handle()
    {
        $activeSubs = HotelSubscription::whereIn('status', ['active', 'trial', 'grace_period'])->get();

        /** @var HotelSubscription $sub */
        foreach ($activeSubs as $sub) {
            $end = $sub->current_period_end;
            if (!$end) continue;

            if ($end->copy()->subDays(3)->isToday()) {
                $this->info("T-3 Warning sent for Hotel: " . $sub->hotel_id);
            }

            if ($end->copy()->subDays(1)->isToday()) {
                $this->info("T-1 Warning sent for Hotel: " . $sub->hotel_id);
            }
            
            if ($end->copy()->addHours(24)->isPast()) {
                $oldStatus = $sub->status;
                $sub->update(['status' => 'suspended']);
                \App\Services\AuditLogService::log(
                    'hotel_subscription',
                    $sub->id,
                    'suspended_by_watchdog',
                    ['status' => $oldStatus],
                    ['status' => 'suspended'],
                    '24 hours passed after expiry. Automated suspension.'
                );
                $this->info("Suspended Hotel: " . $sub->hotel_id);
            }
        }
    }
}
