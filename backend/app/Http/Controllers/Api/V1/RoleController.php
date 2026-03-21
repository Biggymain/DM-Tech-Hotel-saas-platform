<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles for the current hotel.
     */
    public function index(Request $request)
    {
        $excludedSlugs = ['superadmin', 'super-admin', 'groupadmin', 'group-admin', 'hotelowner', 'hotel-owner'];

        $roles = Role::where(function($query) use ($request) {
                $query->where('hotel_id', $request->user()->hotel_id)
                      ->orWhere('is_system_role', true);
            })
            ->whereNotIn('slug', $excludedSlugs)
            ->get()
            ->unique('slug');

        return response()->json($roles->values());
    }
}
