<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;





use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;

class AuthController extends Controller
{
    /**
     * Authenticate Admin User & Issue Token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with(['roles', 'hotel', 'hotelGroup'])->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials provided.'],
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

        return response()->json([
            'user'    => $this->formatUser($user),
            'token'   => $token,
            'message' => 'Login successful',
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
        return response()->json($this->formatUser($request->user()->load(['roles', 'hotel', 'hotelGroup'])));
    }

    public function me(Request $request)
    {
        return $this->user($request);
    }

    /**
     * Return a consistent user payload to the frontend, including slugs needed
     * for role-based redirect routing in AuthProvider.tsx.
     */
    private function formatUser(User $user): array
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
            'hotel_slug'      => $user->hotel?->slug,
            'hotel_group_id'  => $user->hotel_group_id ?? null,
            'outlet_id'       => $user->outlet_id ?? null,
            'is_super_admin'  => (bool) $user->is_super_admin,
            'must_change_password' => (bool) $user->must_change_password,
            'roles'           => $user->roles->map(fn($r) => [
                'id'   => $r->id,
                'name' => $r->name,
                'slug' => strtolower(str_replace(' ', '-', $r->name)),
            ])->values(),
            'active_modules'  => $active_modules,
            'permissions'     => $permissions,
        ];
    }

    /**
     * Forgot Password - Send Reset Link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // In a real app we'd use Password::broker()->sendResetLink(...)
        // But for this SaaS requirement we need signed tokens.
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success anyway to prevent email enumeration
            return response()->json(['message' => 'If your email exists, a reset link has been sent.']);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Notify user (would send an email in production)
        // For simulation/testing, we can return the token in non-production or log it
        \Illuminate\Support\Facades\Log::info("Password reset token for {$user->email}: {$token}");

        return response()->json(['message' => 'Password reset link sent to your email.']);
    }

    /**
     * Reset Password using Token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !isset($record->token) || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        // Validity check (1 hour)
        if (now()->parse($record->created_at)->addHour()->isPast()) {
            return response()->json(['message' => 'Token has expired.'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Audit Log
        AuditLog::create([
            'hotel_id' => $user->hotel_id,
            'user_id' => $user->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'change_type' => 'password_reset',
            'reason' => 'User requested password reset',
            'source' => 'api'
        ]);

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
