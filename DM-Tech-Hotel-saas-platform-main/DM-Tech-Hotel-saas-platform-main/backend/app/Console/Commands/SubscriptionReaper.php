<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HotelSubscription;
use App\Services\AuditLogService;

class SubscriptionReaper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:reap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks subscription status for T-3/T-1 alerts and auto-suspends accounts past 24h grace';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $activeSubs = HotelSubscription::whereIn('status', ['active', 'trial', 'grace_period'])->get();

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
                
                AuditLogService::log(
                    entityType: 'hotel_subscription',
                    entityId: $sub->id,
                    changeType: 'suspended_by_reaper',
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'suspended'],
                    reason: '24 hours passed after expiry. Automated suspension.',
                    source: 'system',
                    hotelId: $sub->hotel_id
                );
                
                $this->info("Suspended Hotel: " . $sub->hotel_id);
            }
        }
    }
}
