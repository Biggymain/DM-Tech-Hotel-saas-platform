<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Display a listing of the staff members.
     */
    public function index(Request $request)
    {
        $hotelId = app()->bound('tenant_id') ? app('tenant_id') : $request->user()->hotel_id;
        $user = $request->user();

        $query = User::with(['roles', 'outlet']);
        
        // If the user has an outlet_id (like an Outlet Manager), filter by that outlet only
        if ($user->outlet_id && !$user->isGroupAdmin() && !$user->is_super_admin) {
            $query->where(function($q) use ($user) {
                $q->where('outlet_id', $user->outlet_id);
            });
        }

        if ($hotelId) {
             $query->where(function($q) use ($hotelId) {
                 $q->where('hotel_id', $hotelId);
             });
        } elseif ($user->hotel_group_id) {
             $query->where(function($q) use ($user) {
                 $q->where('hotel_group_id', $user->hotel_group_id);
             });
        } else {
             // Fallback for super admin if no hotel_id/group_id provided
             if (!$user->is_super_admin) {
                 return response()->json(['error' => 'No branch context'], 400);
             }
        }

        if ($request->has('on_duty')) {
            $query->where('is_on_duty', $request->boolean('on_duty'));
        }

        $users = $query->where(function($q) {
            $q->where('is_super_admin', false);
        })->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Store a newly created staff member in storage.
     */
    public function store(Request $request)
    {
        $hotelId = app()->bound('tenant_id') ? app('tenant_id') : $request->user()->hotel_id;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'required|exists:roles,id',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        // Generate a 12-character random temporary password
        $tempPassword = \Illuminate\Support\Str::random(12);

        $user = User::create([
            'name' => strip_tags($validated['name']),
            'email' => $validated['email'],
            'password' => bcrypt($tempPassword),
            'hotel_id' => $hotelId,
            'hotel_group_id' => $request->user()->hotel_group_id,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'must_change_password' => true,
        ]);

        // Attach role
        $user->roles()->attach($validated['role_id'], [
            'hotel_id' => $hotelId
        ]);

        // Send Onboarding Notification (Email with Temp Password)
        $user->notify(new \App\Notifications\StaffOnboardedNotification($user->name, $tempPassword));

        return response()->json([
            'message' => 'Staff member onboarded successfully. Credentials sent to their email.',
            'data' => $user->load(['roles', 'outlet'])
        ], 201);
    }

    /**
     * Toggle the authenticated user's duty status.
     * POST /api/v1/staff/toggle-duty
     */
    public function toggleDuty(Request $request)
    {
        $user = $request->user();
        $user->is_on_duty = !$user->is_on_duty;
        $user->last_duty_toggle_at = now();
        $user->save();

        return response()->json([
            'message' => $user->is_on_duty ? 'You are now on duty.' : 'You are now off duty.',
            'is_on_duty' => $user->is_on_duty,
            'last_duty_toggle_at' => $user->last_duty_toggle_at,
        ]);
    }
}
