<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * channels.php — Laravel Echo / Pusher Channel Authorization
 *
 * Private channels require authentication. Laravel Echo sends a POST to
 * /broadcasting/auth with the user's session cookie, and this file
 * determines whether the authenticated user is allowed to subscribe.
 *
 * Channel naming convention:
 *   private-hotel.{hotel_id}.station.{station}  → KDS tablets per station
 *   private-hotel.{hotel_id}.waiter.{waiter_id} → Individual waiter's alert channel
 *   private-hotel.{hotel_id}.dashboard          → Admin dashboard live feed
 */

// Default user notification channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── Station KDS channels ──────────────────────────────────────────────────────
// Only authenticated users in the same branch OR the Group Owner can subscribe.
Broadcast::channel('hotel.{hotelId}.branch.{branchId}.station.{stationId}', function ($user, $hotelId, $branchId, $stationId) {
    if ($user->is_super_admin) return true;

    // Support for Group Owners (bypass branch check if they own the hotel)
    if (!empty($user->hotel_group_id) && !$user->hotel_id) {
        $ownsHotel = \App\Models\Hotel::where('id', $hotelId)
            ->where('hotel_group_id', $user->hotel_group_id)
            ->exists();
        if ($ownsHotel) return ['id' => $user->id, 'name' => $user->name, 'station_id' => $stationId];
    }

    if ((int) $user->hotel_id !== (int) $hotelId) return false;
    if ((int) ($user->branch_id ?? $user->hotel_id) !== (int) $branchId) return false;
    
    // If they have a station assigned, they must match it (unless manager)
    if ($user->kitchen_station_id && (int) $user->kitchen_station_id !== (int) $stationId && !$user->isBranchManager()) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name, 'station_id' => $stationId];
});

// ── Kitchen Display System (KDS) common channel ──────────────────────────────
Broadcast::channel('hotel.{hotelId}.branch.{branchId}.kds', function ($user, $hotelId, $branchId) {
    if ($user->is_super_admin) return true;

    // Support for Group Owners
    if (!empty($user->hotel_group_id) && !$user->hotel_id) {
        $ownsHotel = \App\Models\Hotel::where('id', $hotelId)
            ->where('hotel_group_id', $user->hotel_group_id)
            ->exists();
        if ($ownsHotel) return ['id' => $user->id, 'name' => $user->name];
    }

    if ((int) $user->hotel_id !== (int) $hotelId) return false;
    if ((int) ($user->branch_id ?? $user->hotel_id) !== (int) $branchId) return false;

    return ['id' => $user->id, 'name' => $user->name];
});

// ── Waiter notification channels ─────────────────────────────────────────────
Broadcast::channel('hotel.{hotelId}.waiter.{waiterId}', function ($user, $hotelId, $waiterId) {
    return (int) $user->hotel_id === (int) $hotelId
        && (int) $user->id === (int) $waiterId;
});

// ── POS / Outlet Manager channel ─────────────────────────────────────────────
// Receives NewOrderClaimed events so Outlet Managers can see live waitress assignments.
// Access is granted to hotel staff in the same hotel AND outlet (branch).
Broadcast::channel('hotel.{hotelId}.branch.{branchId}.pos', function ($user, $hotelId, $branchId) {
    if ($user->is_super_admin) return true;

    // Group owners get access across their hotels
    if (!empty($user->hotel_group_id) && !$user->hotel_id) {
        $ownsHotel = \App\Models\Hotel::where('id', $hotelId)
            ->where('hotel_group_id', $user->hotel_group_id)
            ->exists();
        if ($ownsHotel) return ['id' => $user->id, 'name' => $user->name];
    }

    if ((int) $user->hotel_id !== (int) $hotelId) return false;

    // Outlet Managers must belong to the same outlet/branch
    $userBranch = $user->outlet_id ?? $user->branch_id ?? $user->hotel_id;
    if ((int) $userBranch !== (int) $branchId) return false;

    return ['id' => $user->id, 'name' => $user->name];
});
