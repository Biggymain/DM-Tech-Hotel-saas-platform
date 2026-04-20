<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionInvoice;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Create a new subscription (usually with trial)
     */
    public function createSubscription(Hotel $hotel, SubscriptionPlan $plan): HotelSubscription
    {
        return DB::transaction(function () use ($hotel, $plan) {
            $subscription = HotelSubscription::create([
                'hotel_id' => $hotel->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14), // Initial trial period
            ]);

            AuditLogService::log(
                'hotel_subscription',
                $subscription->id,
                'created',
                null,
                $subscription->toArray(),
                "Started trial for {$plan->name} plan"
            );

            return $subscription;
        });
    }

    /**
     * Handle successful renewal payment
     */
    public function recordPayment(HotelSubscription $subscription, float $amount, string $gateway, string $reference): SubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription, $amount, $gateway, $reference) {
            $invoice = SubscriptionInvoice::create([
                'hotel_id' => $subscription->hotel_id,
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'paid',
                'payment_gateway' => $gateway,
                'payment_reference' => $reference,
                'paid_at' => now(),
            ]);

            // Extend period based on plan billing cycle
            $days = $subscription->plan->billing_cycle === 'yearly' ? 365 : 30;
            
            $start = now();
            $end = now()->addDays($days);

            $subscription->update([
                'status' => 'active',
                'current_period_start' => $start,
                'current_period_end' => $end,
                'grace_period_ends_at' => null, // Reset grace period
            ]);

            AuditLogService::log(
                'hotel_subscription',
                $subscription->id,
                'renewed',
                null,
                ['invoice_id' => $invoice->id, 'new_expiry' => $end->toDateString()]
            );

            return $invoice;
        });
    }

    /**
     * Handle payment failure and apply grace period
     */
    public function handlePaymentFailure(HotelSubscription $subscription)
    {
        $subscription->update([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(14),
        ]);

        AuditLogService::log(
            'hotel_subscription',
            $subscription->id,
            'grace_period_started',
            null,
            ['grace_period_ends_at' => $subscription->grace_period_ends_at->toDateString()],
            'Payment failed, 14-day grace period applied.'
        );

        // Notify hotel admin (logic would go in a listener or here)
        Log::warning("Subscription payment failed for Hotel #{$subscription->hotel_id}. Grace period started.");
    }

    /**
     * Suspend subscription after grace period remains unpaid
     */
    public function suspendSubscription(HotelSubscription $subscription)
    {
        $subscription->update(['status' => 'suspended']);

        AuditLogService::log(
            'hotel_subscription',
            $subscription->id,
            'suspended',
            null,
            null,
            'Subscription suspended after grace period expiry.'
        );

        Log::error("Subscription SUSPENDED for Hotel #{$subscription->hotel_id}.");
    }

    /**
     * Synchronize and check for expiries (Cron Job entry point)
     */
    public function checkExpiries()
    {
        // 1. Check for expired trials/active periods without grace
        $expiring = HotelSubscription::whereIn('status', ['active', 'trial'])
            ->where('current_period_end', '<', now())
            ->get();

        /** @var HotelSubscription $sub */
        foreach ($expiring as $sub) {
            $this->handlePaymentFailure($sub);
        }

        // 2. Check for expired grace periods
        $toSuspend = HotelSubscription::where('status', 'grace_period')
            ->where('grace_period_ends_at', '<', now())
            ->get();

        /** @var HotelSubscription $sub */
        foreach ($toSuspend as $sub) {
            $this->suspendSubscription($sub);
        }
    }

    /**
     * Calculate monthly dynamic rate for a group and its branches.
     */
    public function calculateDynamicRate(int $groupId): float
    {
        // 1. Fetch group branches with their tier
        $group = \App\Models\HotelGroup::with(['branches' => function ($query) {
            $query->withoutGlobalScopes()->with('tier');
        }])->find($groupId);

        if (!$group) return 0.0;

        $totalMonthly = 0.0;
        $activeBranches = 0;

        foreach ($group->branches as $branch) {
            if ($branch->tier) {
                $totalMonthly += (float)$branch->tier->price;
                $activeBranches++;
            } else {
                $subscription = HotelSubscription::where('hotel_id', $branch->id)
                    ->whereIn('status', ['active', 'trial', 'grace_period'])
                    ->latest()
                    ->first();

                if ($subscription && $subscription->plan) {
                    $totalMonthly += (float)$subscription->plan->price;
                    $activeBranches++;
                }
            }
        }

        // 2. Multi-Branch Limit Reward
        if ($activeBranches > 1) {
            $discount = \App\Models\SystemSetting::getSetting('multi_branch_discount_rate', 0.10);
            $totalMonthly -= ($totalMonthly * $discount);
        }

        // 3. One-Time Licensing Fee
        if (!$group->is_licensed) {
            $licensingFee = \App\Models\SystemSetting::getSetting('group_licensing_fee', 1000.00); // 1000 or whatever default
            $totalMonthly += (float)$licensingFee;
        }

        return (float) $totalMonthly;
    }
}
