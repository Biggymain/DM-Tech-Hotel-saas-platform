<?php

namespace App\Console\Commands\Auth;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreHardwareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fortress:restore-hardware {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets a user to the Pending Marriage state for hardware restoration.';

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

        DB::transaction(function () use ($user) {
            $user->update([
                'hardware_hash' => null,
                'pending_hardware_hash' => null,
                'is_relinking' => true,
                'is_approved' => false,
            ]);

            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'change_type' => 'hardware_restoration_triggered',
                'reason' => 'Hardware Restoration: User reset to Pending Marriage state.',
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info("----------------------------------------------------");
        $this->info("🔄 HARDWARE RESTORATION TRIGGERED: {$user->email}");
        $this->info("User set to 'Pending Marriage' state.");
        $this->info("Next login will silently capture new hardware details.");
        $this->info("----------------------------------------------------");

        return 0;
    }
}
