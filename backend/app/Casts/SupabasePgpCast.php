<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Exceptions\SecurityKeyMismatchException;

class SupabasePgpCast implements CastsAttributes
{
    protected $passphrase;

    protected function getPassphrase(): ?string
    {
        $passphrase = null;
        try {
            $passphrase = config('fortress.dev_passphrase') ?? config('services.supabase.passphrase');
        } catch (\Exception $e) {
            // App might not be fully bootstrapped in some test scenarios
        }

        return $passphrase;
    }

    protected function ensurePassphrase(): string
    {
        $passphrase = $this->getPassphrase();
        
        if (empty($passphrase)) {
            throw new SecurityKeyMismatchException('DEV_PASSPHRASE is missing in config or .env. Digital Fortress compromised.');
        }

        return $passphrase;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // PGP Circuit Breaker & Support TTL Gate
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();

            // 0. Primary Kill-Switch (Approved state from GUI)
            // If the user's hardware marriage or identity is NOT approved, return [LOCKED_BY_FORTRESS]
            if (!$user->is_approved && !$user->isSuperAdmin()) {
                return '[LOCKED_BY_FORTRESS]';
            }

            // 1. SuperAdmins always have access (The God Mode Override)
            if ($user->isSuperAdmin()) {
                // Proceed to decryption logic below
            } 
            // 2. SupportStaff MUST have an active session flag
            elseif ($user->hasRole('supportstaff')) {
                if (session('support_session_active') !== true) {
                    return '[LOCKED_BY_FORTRESS]';
                }
            }
        }

        // Decryption via Supabase RPC (using bytea value)
        $result = DB::connection('supabase')->selectOne(
            "SELECT decrypt_sensitive_data(?, ?) as plain_text",
            [$value, $this->ensurePassphrase()]
        );

        if (is_null($result->plain_text)) {
            throw new SecurityKeyMismatchException('Failed to decrypt data. The provided passphrase might be incorrect.');
        }

        return $result->plain_text;
    }

    /**
     * Prepare the given value for storage (Encrypt via Supabase RPC).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // Encryption via Supabase RPC (returns bytea)
        $result = DB::connection('supabase')->selectOne(
            "SELECT encrypt_sensitive_data(?, ?) as encrypted_text",
            [$value, $this->ensurePassphrase()]
        );

        return $result->encrypted_text;
    }
}
