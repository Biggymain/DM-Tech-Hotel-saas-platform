<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Exceptions\RootImmunityException;
use App\Services\HardwareFingerprintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeveloperController extends Controller
{
    protected HardwareFingerprintService $fingerprintService;

    public function __construct(HardwareFingerprintService $fingerprintService)
    {
        $this->fingerprintService = $fingerprintService;
    }

    /**
     * Register the current terminal as a Master device.
     * This creates/updates a record in the local hardware_devices table.
     */
    public function registerTerminal(Request $request)
    {
        try {
            // Automatic Hardware ID capture: Header takes priority, otherwise generate from system fingerprint
            $hash = $request->header('X-Hardware-Id') ?? $this->fingerprintService->generateHash();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Fingerprint Capture Failed',
                'message' => $e->getMessage()
            ], 500);
        }

        \App\Models\HardwareDevice::updateOrCreate(
            ['hardware_hash' => $hash],
            [
                'hotel_id' => null, // Master terminals exist at system level
                'hardware_uuid' => 'PHOENIX-' . substr($hash, 0, 8),
                'device_name' => 'Developer Master Terminal (' . ($request->header('User-Agent') ?? 'Unknown') . ')',
                'access_level' => 'master',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        Log::info("Phoenix Master Marriage Successful: Developer Terminal sealed with hash {$hash}");

        return response()->json([
            'message' => 'Phoenix Master Marriage Successful',
            'hardware_hash' => $hash,
            'access_level' => 'master',
            'status' => 'sealed'
        ]);
    }

    /**
     * List all Master Admins.
     */
    public function listMasterUsers()
    {
        $users = User::whereHas('roles', function($q) {
            $q->where('slug', 'master_admin');
        })->orWhere('is_super_admin', true)
        ->with('roles')
        ->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Create a new Master Admin (Default: Pending Approval).
     */
    public function storeMasterUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_approved' => false, // Non-destructive Waiting Room
            'is_super_admin' => false,
        ]);

        $role = Role::firstOrCreate(['slug' => 'master_admin'], ['name' => 'Master Admin', 'is_system_role' => true]);
        $user->roles()->attach($role->id);

        return response()->json([
            'message' => 'Master Admin created and placed in the Waiting Room.',
            'user' => $user
        ], 201);
    }

    /**
     * Approve a pending Master Admin.
     */
    public function approveUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $user->update(['is_approved' => true]);

        Log::info("Master Approval: User {$user->email} approved by " . auth()->user()->email);

        return response()->json(['message' => "User {$user->name} is now authorized."]);
    }

    /**
     * Delete a Master Admin (Root Protected).
     */
    public function destroyUser($id)
    {
        if ((int)$id === 1) {
            throw new RootImmunityException();
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User removed from the Digital Fortress.']);
    }

    /**
     * Get the status of the current developer terminal.
     */
    public function status(Request $request)
    {
        $device = $request->attributes->get('hardware_device_record');
        
        return response()->json([
            'status' => 'verified',
            'hardware_id' => $device['hardware_hash'] ?? $request->header('X-Hardware-Id'),
            'access_level' => 'master',
            'device_name' => $device['device_name'] ?? 'Unknown Master Terminal',
        ]);
    }
}
