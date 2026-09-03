<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class FortressLockService
{
    private string $lockFile;

    public function __construct()
    {
        $this->lockFile = storage_path('framework/fortress.lock');
    }

    /**
     * Check if the system is currently under lockdown.
     */
    public function isLocked(): bool
    {
        return File::exists($this->lockFile);
    }

    /**
     * Trigger a system-wide lockdown.
     */
    public function triggerLock(): void
    {
        if (!$this->isLocked()) {
            File::put($this->lockFile, json_encode([
                'locked_at' => now()->toIso8601String(),
                'reason' => 'Security integrity breach: Decryption failure detected.',
            ]));
        }
    }

    /**
     * Release the system lockdown.
     */
    public function releaseLock(): bool
    {
        return File::delete($this->lockFile);
    }
}
