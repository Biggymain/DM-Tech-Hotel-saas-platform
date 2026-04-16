<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HardwareMarriageService
{
    /**
     * Marry a user to a specific hardware fingerprint.
     * This seals their vault using the system-level passphrase.
     */
    public function marry(User $user, ?string $explicitHash = null): void
    {
        $oldHash = $user->hardware_hash;
        $newHash = $explicitHash ?? $user->pending_hardware_hash;

        if (!$newHash) {
            Log::warning("Hardware Marriage attempt failed for user {$user->id}: No hash available.");
            return;
        }

        DB::transaction(function () use ($user, $oldHash, $newHash) {
            // 1. Finalize the marriage
            $user->hardware_hash = $newHash;
            $user->pending_hardware_hash = null;
            $user->is_approved = true;
            $user->is_relinking = false;
            
            // To "seal the vault" using the system passphrase, we re-save the password.
            // Since User model uses SupabasePgpCast, and it pulls from config('fortress.dev_passphrase')
            // (after our upcoming update to SupabasePgpCast), this will re-encrypt it.
            // For now, we manually trigger the update.
            $user->save();

            // 2. Audit Logging
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'change_type' => 'hardware_marriage',
                'old_values' => json_encode(['hardware_hash' => $oldHash]),
                'new_values' => json_encode(['hardware_hash' => $newHash]),
                'reason' => 'Silent Hardware Marriage: Vault sealed and identity approved.',
                'source' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Log::info("🔒 Hardware Marriage Successful for User: {$user->email} (Hash: {$newHash})");
    }
}
