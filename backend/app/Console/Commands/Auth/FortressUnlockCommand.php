<?php

namespace App\Console\Commands\Auth;

use Illuminate\Console\Command;
use App\Services\FortressLockService;

class FortressUnlockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fortress:unlock {--master-key= : The master passphrase (DEV_PASSPHRASE) required to unlock the system}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release the Digital Fortress lockdown using the master passphrase.';

    /**
     * Execute the console command.
     */
    public function handle(FortressLockService $lockService)
    {
        $masterKey = $this->option('master-key');
        $expectedKey = config('services.supabase.passphrase');

        if (empty($masterKey)) {
            $this->error('The --master-key option is required.');
            return 1;
        }

        if (empty($expectedKey)) {
            $this->error('The system is in an unconfigured state (DEV_PASSPHRASE missing). Recovery impossible through this channel.');
            return 1;
        }

        if ($masterKey !== $expectedKey) {
            $this->error('!!! INVALID RECOVERY KEY !!!');
            $this->warn('Unauthorized recovery attempt logged.');
            return 1;
        }

        if (!$lockService->isLocked()) {
            $this->info('The system is not currently locked.');
            return 0;
        }

        if ($lockService->releaseLock()) {
            $this->info('----------------------------------------------------');
            $this->info('🔓 DIGITAL FORTRESS LOCK RELEASED');
            $this->info('----------------------------------------------------');
            $this->info('The system integrity has been manually restored.');
            return 0;
        }

        $this->error('Failed to release the lock. Check file permissions in storage/framework.');
        return 1;
    }
}
