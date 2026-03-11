<?php

namespace App\Http\Controllers\API\V1;

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
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials provided.'],
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        // Dispatch Login Event for Alerts
        event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful'
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
    public function user(Request $request)
    {
        return response()->json($request->user());
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
