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
// Only authenticated users in the same hotel can subscribe.
// Staff with chef/kitchen roles can subscribe to any station in their hotel.
Broadcast::channel('hotel.{hotelId}.station.{station}', function ($user, $hotelId, $station) {
    // Must belong to this hotel (or be a super admin)
    if ((int) $user->hotel_id !== (int) $hotelId && !$user->is_super_admin) {
        return false;
    }

    // Determine which stations this user can listen to based on their role
    $userRoles = $user->roles->pluck('slug')->map(fn($s) => strtolower($s))->toArray();

    $kitchenRoles  = ['chef', 'kitchen-manager', 'cook', 'bartender', 'barista'];
    $managerRoles  = ['general-manager', 'hotelowner', 'receptionist'];

    $isKitchenStaff = array_intersect($kitchenRoles, $userRoles);
    $isManager      = array_intersect($managerRoles, $userRoles);

    // Managers see all stations; kitchen staff see their assigned or all stations
    if ($isManager || $user->is_super_admin || $isKitchenStaff) {
        return ['id' => $user->id, 'name' => $user->name, 'station' => $station];
    }

    return false;
});

// ── Waiter notification channels ─────────────────────────────────────────────
// A waiter can only subscribe to their own channel.
Broadcast::channel('hotel.{hotelId}.waiter.{waiterId}', function ($user, $hotelId, $waiterId) {
    return (int) $user->hotel_id === (int) $hotelId
        && (int) $user->id === (int) $waiterId;
});

// ── Hotel-wide dashboard channel ─────────────────────────────────────────────
// Managers and super admins only
Broadcast::channel('hotel.{hotelId}.dashboard', function ($user, $hotelId) {
    $isAdmin = $user->is_super_admin
        || ((int) $user->hotel_id === (int) $hotelId && $user->roles->pluck('slug')->contains('general-manager'));
    return $isAdmin ? ['id' => $user->id, 'name' => $user->name] : false;
});
