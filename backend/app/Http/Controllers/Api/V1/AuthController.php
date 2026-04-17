<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;





use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;
use App\Services\HardwareFingerprintService;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\SecurityKeyMismatchException;

class AuthController extends Controller
{
    /**
     * Authenticate Admin User & Issue Token
     */
    public function login(Request $request, HardwareFingerprintService $fingerprintService)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with(['roles', 'hotel', 'hotelGroup'])->where('email', $request->email)->first();

        if ($user && $user->is_relinking) {
            $user->pending_hardware_hash = $fingerprintService->generateHash();
            $user->password = $request->password; // Re-seals the vault with current system passphrase
            $user->save();
        }

        try {
            // Hardware Marriage: Super/Group Admins must match their registered device
            if ($user && ($user->is_super_admin || $user->isGroupAdmin())) {
                $currentFingerprint = $fingerprintService->generateHash();
                if ($user->hardware_hash !== $currentFingerprint) {
                    // Log CRITICAL event for SIEM (Severity 12)
                    Log::channel('siem')->critical('Hardware Mismatch detected during login.', [
                        'severity_score' => 12,
                        'user_id' => $user->id,
                        'expected_hash' => substr($user->hardware_hash, 0, 8) . '...',
                    ]);

                    \App\Services\AuditLogService::log(
                        'user',
                        $user->id,
                        'hardware_mismatch',
                        ['hash' => $user->hardware_hash],
                        ['hash' => $currentFingerprint],
                        'Hardware Handshake Mismatch detected during login.'
                    );
                    
                    throw ValidationException::withMessages([
                        'email' => ['Hardware Handshake Mismatch: This device is not authorized for this identity.'],
                    ]);
                }
            }

            if (!$user || $user->password !== $request->password) {
                throw ValidationException::withMessages([
                    'email' => ['Invalid credentials provided.'],
                ]);
            }
        } catch (SecurityKeyMismatchException $e) {
            // Hardware/Security Handshake failed!
            throw ValidationException::withMessages([
                'email' => ['System security lockdown: Decryption failed. Please contact support.'],
            ]);
        }

        $frontendPort = $request->header('X-Frontend-Port');

        // Strict Port-to-Role validation
        if ($frontendPort === '3000' && !$user->is_super_admin) {
            abort(403, 'Forbidden: Super Admin role required for this port.');
        }

        if ($frontendPort === '3002') {
            $hasAuthorizedRole = $user->roles()->whereIn('slug', ['generalmanager', 'hotelowner', 'manager', 'receptionist', 'reception'])->exists();
            if (!$hasAuthorizedRole) {
                abort(403, 'Forbidden: Manager or Receptionist role required for this port.');
            }
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

        return response()->json([
            'user'    => $this->formatUser($user, $request),
            'token'   => $token,
            'message' => 'Login successful',
        ]);
    }

    /**
     * Authenticate Staff via 4-6 Digit PIN
     */
    public function staffPinLogin(Request $request)
    {
        $request->validate([
            'staff_id' => 'required',
            'pin'      => 'required|string|min:4|max:6',
        ]);

        $user = User::with(['roles', 'hotel', 'hotelGroup'])->find($request->staff_id);

        try {
            if (!$user || $user->pin_code !== $request->pin) {
                throw ValidationException::withMessages([
                    'pin' => ['Invalid PIN code.'],
                ]);
            }
        } catch (SecurityKeyMismatchException $e) {
            // Hardware/Security Handshake failed!
            throw ValidationException::withMessages([
                'pin' => ['Internal security lockout: Fingerprint verification failed.'],
            ]);
        }

        $frontendPort = $request->header('X-Frontend-Port');

        if ($frontendPort !== '3003') {
            abort(403, 'Forbidden: Staff operations must be performed on the designated port (3003).');
        }

        $token = $user->createToken('staff-pin-token')->plainTextToken;

        event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

        return response()->json([
            'user'    => $this->formatUser($user, $request),
            'token'   => $token,
            'message' => 'Staff PIN login successful',
        ]);
    }

    /**
     * Revoke Token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get Current Authenticated User
     */
    /**
     * GET /api/v1/auth/user and /api/v1/auth/me (AuthProvider calls /me)
     */
    public function user(Request $request)
    {
        return response()->json($this->formatUser($request->user()->load(['roles', 'hotel', 'hotelGroup']), $request));
    }

    public function me(Request $request)
    {
        return $this->user($request);
    }

    /**
     * Setup Staff PIN and Password (Initial Onboarding)
     */
    public function setupStaff(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'pin' => 'required|string|size:4|regex:/^[0-9]+$/',
        ]);

        $user = $request->user();
        
        $user->update([
            'password' => $request->password,
            'pin_code' => $request->pin,
            'password_changed_at' => now(),
            'must_change_password' => false,
        ]);

        // Audit Log
        AuditLog::create([
            'hotel_id' => $user->hotel_id,
            'user_id' => $user->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'change_type' => 'staff_setup',
            'reason' => 'Staff completed initial security onboarding (Password & PIN)',
            'source' => 'api'
        ]);

        return response()->json([
            'message' => 'Security setup complete. You now have full access.',
            'user' => $this->formatUser($user, $request),
        ]);
    }

    /**
     * Return a consistent user payload to the frontend, including slugs needed
     * for role-based redirect routing in AuthProvider.tsx.
     */
    private function formatUser(User $user, ?Request $request = null): array
    {
        $active_modules = [];
        $permissions = [];

        if ($user->hotel_id) {
            $active_modules = DB::table('hotel_modules')
                ->join('modules', 'hotel_modules.module_id', '=', 'modules.id')
                ->where('hotel_modules.hotel_id', $user->hotel_id)
                ->where('hotel_modules.is_enabled', true)
                ->pluck('modules.slug')
                ->toArray();
        }

        if ($user->is_super_admin) {
            $permissions = ['*'];
        } else {
            foreach ($user->roles as $role) {
                $rolePerms = DB::table('role_permissions')
                    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_permissions.role_id', $role->id)
                    ->pluck('permissions.slug')
                    ->toArray();
                $permissions = array_merge($permissions, $rolePerms);
            }
            $permissions = array_values(array_unique($permissions));
        }

        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'hotel_id'        => $user->hotel_id,
            'hotel'           => $user->hotel ? [
                'id' => $user->hotel->id,
                'name' => $user->hotel->name,
                'departments' => $user->hotel->departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])
            ] : null,
            'hotel_slug'      => $user->hotel?->slug,
            'hotel_group_id'  => $user->hotel_group_id ?? null,
            'outlet_id'       => $user->outlet_id ?? null,
            'is_super_admin'  => (bool) $user->is_super_admin,
            'must_change_password' => (bool) $user->must_change_password,
            'roles'           => $user->roles->map(fn($r) => [
                'id'   => $r->id,
                'name' => $r->name,
                'slug' => $r->slug ?? strtolower(str_replace(' ', '', $r->name)),
            ])->values(),
            'active_modules'  => $active_modules,
            'requires_onboarding' => (bool) ($request?->header('X-Frontend-Port') === '3003' && ($user->password_changed_at === null || $user->pin_code === null)),
            'license'         => $this->getLicenseData(),
        ];
    }

    /**
     * Pull license data from SentryMiddleware cache
     */
    private function getLicenseData(): array
    {
        $fingerprintService = app(HardwareFingerprintService::class);
        $hardwareHash = $fingerprintService->generateHash();
        $cacheKey = "licensing_sentry_{$hardwareHash}";

        $license = Cache::get($cacheKey);

        if (!$license) {
            return [
                'status' => 'PENDING',
                'expires_at' => null,
                'days_remaining' => 0
            ];
        }

        $expiresAt = isset($license['expires_at']) ? \Carbon\Carbon::parse($license['expires_at']) : null;
        $daysRemaining = $expiresAt ? now()->diffInDays($expiresAt, false) : 0;

        return [
            'status' => $license['status'] ?? 'UNKNOWN',
            'expires_at' => $license['expires_at'] ?? null,
            'manager_email' => $license['manager_email'] ?? null,
            'owner_email' => $license['owner_email'] ?? null,
            'days_remaining' => (int) $daysRemaining,
        ];
    }

    /**
     * Forgot Password - Send Reset Link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success anyway to prevent email enumeration
            return response()->json(['message' => 'If your email exists, a reset code has been sent.']);
        }

        // Generate 6-digit OTP
        $otp = (string) rand(100000, 999999);

        // Store OTP in password_reset_tokens table (Direct assignment - relying on transport security or PGP if moved later)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $otp, 'created_at' => now()]
        );

        // Notify user (simulating email via log)
        Log::info("🔐 Password Reset OTP for {$user->email}: {$otp}");

        return response()->json([
            'message' => 'A 6-digit password reset code has been sent to your email.',
            'dev_otp' => config('app.debug') ? $otp : null // Optional: return OTP in debug mode for easier testing
        ]);
    }

    /**
     * Reset Password using Token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string|size:6', // Now expecting a 6-digit OTP
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        // Validate OTP (Direct comparison - removal of legacy hashing)
        if (!$record || !isset($record->token) || $request->token !== $record->token) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 422);
        }

        // Validity check (15 minutes for OTP)
        if (now()->parse($record->created_at)->addMinutes(15)->isPast()) {
            return response()->json(['message' => 'Reset code has expired.'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => $request->password]);

        // Clear the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Audit Log
        AuditLog::create([
            'hotel_id' => $user->hotel_id,
            'user_id' => $user->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'change_type' => 'password_reset',
            'reason' => 'User reset password via OTP',
            'source' => 'api'
        ]);

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
