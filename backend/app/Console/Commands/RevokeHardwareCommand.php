<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeHardwareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fortress:revoke-hardware {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nullifies a users hardware_hash to trigger a GRC-compliant system lockdown for that identity.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $oldHash = $user->hardware_hash;

        DB::transaction(function () use ($user, $oldHash) {
            // 1. Revoke the hardware hash
            $user->update(['hardware_hash' => null]);

            // 2. GRC Compliance: Log to audit_logs
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'change_type' => 'updated',
                'old_values' => json_encode(['hardware_hash' => $oldHash]),
                'new_values' => json_encode(['hardware_hash' => null]),
                'reason' => 'Hardware Revocation: Immediate Identity Lockdown triggered via Fortress CLI.',
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info("----------------------------------------------------");
        $this->info("🔒 HARDWARE REVOKED: {$user->email}");
        $this->info("Action logged in audit_logs for GRC compliance.");
        $this->info("----------------------------------------------------");

        return 0;
    }
}
