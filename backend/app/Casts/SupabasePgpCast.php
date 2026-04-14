<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Exceptions\SecurityKeyMismatchException;

class SupabasePgpCast implements CastsAttributes
{
    protected $passphrase;

    public function __construct()
    {
        // Using config() for reliable management in both production and tests
        $this->passphrase = config('services.supabase.passphrase');

        if (empty($this->passphrase)) {
            throw new SecurityKeyMismatchException('DEV_PASSPHRASE is missing in config or .env. Digital Fortress compromised.');
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // Call the Supabase RPC function for decryption (using bytea value)
        $result = DB::connection('supabase')->selectOne(
            "SELECT decrypt_sensitive_data(?, ?) as plain_text",
            [$value, $this->passphrase]
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

        // Call the Supabase RPC function for encryption (returns bytea)
        $result = DB::connection('supabase')->selectOne(
            "SELECT encrypt_sensitive_data(?, ?) as encrypted_text",
            [$value, $this->passphrase]
        );

        return $result->encrypted_text;
    }
}
