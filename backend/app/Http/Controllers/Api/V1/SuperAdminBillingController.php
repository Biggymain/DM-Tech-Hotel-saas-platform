<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SuperAdminBillingController extends Controller
{
    /**
     * PATCH /api/v1/super-admin/plans
     * Allows Super Admins to update base Tier prices, max room thresholds, and system discount fields dynamically.
     */
    public function updatePlans(Request $request)
    {
        $user = $request->user();
        if (!$user->is_super_admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'plans' => 'array',
            'plans.*.id' => 'required|exists:subscription_plans,id',
            'plans.*.price' => 'numeric',
            'plans.*.max_rooms' => 'nullable|integer',
            'multi_branch_discount_rate' => 'numeric|min:0|max:1',
            'group_license_fee' => 'numeric|min:0'
        ]);

        if (isset($validated['multi_branch_discount_rate'])) {
            \App\Models\SystemSetting::setSetting('multi_branch_discount_rate', $validated['multi_branch_discount_rate'], 'float');
        }

        if (isset($validated['group_license_fee'])) {
            \App\Models\SystemSetting::setSetting('group_license_fee', $validated['group_license_fee'], 'float');
        }

        if (isset($validated['plans'])) {
            foreach ($validated['plans'] as $planData) {
                $plan = \App\Models\SubscriptionPlan::find($planData['id']);
                if (isset($planData['price'])) $plan->price = $planData['price'];
                if (array_key_exists('max_rooms', $planData)) $plan->max_rooms = $planData['max_rooms'];
                $plan->save();
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Subscription plans and system limits synchronized globally.']);
    }
    public function health(Request $request)
    {
        $user = $request->user();
        if (!$user->is_super_admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $branches = \App\Models\Hotel::withoutGlobalScopes()
            ->with(['activeSubscription', 'group'])
            ->get()
            ->map(function ($hotel) {
                $status = $hotel->activeSubscription->status ?? 'none';
                $expiry = $hotel->activeSubscription->current_period_end ?? null;
                
                // Dynamic Status Translation for Super Admin
                if ($status === 'active' && $expiry && $expiry->isPast()) {
                    $status = 'grace_period';
                }

                return [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'group' => $hotel->group->name ?? 'No Group',
                    'subscription_status' => $status,
                    'expiry' => $expiry,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $branches
        ]);
    }
}
