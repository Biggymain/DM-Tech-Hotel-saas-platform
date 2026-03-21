<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\SubscriptionPlan;
use App\Models\HotelSubscription;
use App\Services\SubscriptionService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlatformSubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get available plans
     */
    public function plans()
    {
        return response()->json(SubscriptionPlan::where('is_active', true)->get());
    }

    /**
     * Get current hotel subscription status
     */
    public function current()
    {
        $hotel = Auth::user()->hotel;
        $subscription = $hotel->subscription()->with('plan')->first();
        
        return response()->json([
            'subscription' => $subscription,
            'hotel' => $hotel->only(['id', 'name'])
        ]);
    }

    /**
     * Initiate checkout (simulated)
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'gateway' => 'required|in:stripe,paystack,monnify,paypal',
        ]);

        $hotel = Auth::user()->hotel;
        $plan = SubscriptionPlan::find($validated['plan_id']);

        // In a real app, this would redirect to stripe checkout.
        // For this task, we'll simulate the webhook trigger or just upgrade them.
        
        $subscription = $hotel->subscription;
        if (!$subscription) {
            $subscription = $this->subscriptionService->createSubscription($hotel, $plan);
        }

        // Simulate successful payment record
        $invoice = $this->subscriptionService->recordPayment(
            $subscription,
            $plan->price,
            $validated['gateway'],
            'SUBS_' . uniqid()
        );
        
        AuditLogService::log(
            'SubscriptionInvoice',
            $invoice->id,
            'created',
            null,
            $invoice->toArray(),
            'Payment recorded for subscription checkout'
        );

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription->refresh()->load('plan'),
            'invoice' => $invoice
        ]);
    }

    /**
     * Get invoice history
     */
    public function invoices()
    {
        $hotelId = Auth::user()->hotel_id;
        $invoices = \App\Models\SubscriptionInvoice::where('hotel_id', $hotelId)
            ->latest()
            ->get();

        return response()->json($invoices);
    }

    /**
     * Get platform-wide analytics (for super admin)
     */
    public function analytics()
    {
        // Total Hotels
        $totalHotels = \App\Models\Hotel::count();
        
        // Active Subs
        $activeSubsCount = HotelSubscription::whereIn('status', ['active', 'trial'])->count();
        
        // MRR Calculation
        $mrr = DB::table('hotel_subscriptions')
            ->join('subscription_plans', 'hotel_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('hotel_subscriptions.status', 'active')
            ->where('subscription_plans.billing_cycle', 'monthly')
            ->sum('subscription_plans.price');

        // Add yearly plans as (price/12)
        $mrrYearly = DB::table('hotel_subscriptions')
            ->join('subscription_plans', 'hotel_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('hotel_subscriptions.status', 'active')
            ->where('subscription_plans.billing_cycle', 'yearly')
            ->sum(DB::raw('subscription_plans.price / 12'));

        $totalMrr = $mrr + $mrrYearly;

        $expiredCount = HotelSubscription::whereIn('status', ['suspended', 'cancelled'])->count();

        $hotels = \App\Models\Hotel::with(['subscription.plan'])
            ->get()
            ->map(function($hotel) {
                return [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'status' => $hotel->subscription?->status ?? 'none',
                    'plan' => $hotel->subscription?->plan?->name ?? 'None',
                    'expiry' => $hotel->subscription?->current_period_end?->toDateString() ?? 'N/A'
                ];
            });

        return response()->json([
            'stats' => [
                'total_hotels' => $totalHotels,
                'active_subscriptions' => $activeSubsCount,
                'mrr' => round($totalMrr, 2),
                'expired_accounts' => $expiredCount,
            ],
            'hotels' => $hotels
        ]);
    }
}
