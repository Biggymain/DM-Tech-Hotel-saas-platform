<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportStaffController extends Controller
{
    /**
     * Handle a moderated support staff signup.
     */
    public function signup(Request $request, \App\Services\HardwareFingerprintService $fingerprintService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $pendingHash = $fingerprintService->generateHash();

        return DB::transaction(function () use ($validated, $pendingHash) {
            // Create the pending user
            $user = User::create([
                'name' => strip_tags($validated['name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_approved' => false, // explicitly pending
                'must_change_password' => false,
                'pending_hardware_hash' => $pendingHash,
            ]);

            // Assign supportstaff role
            $role = Role::where('slug', 'supportstaff')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Support access requested. Your account is pending approval by the Super Admin.',
                'data' => [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_approved' => false
                    ]
                ]
            ], 201);
        });
    }
}
