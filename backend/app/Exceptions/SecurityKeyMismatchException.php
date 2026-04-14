<?php

namespace App\Exceptions;

use Exception;

class SecurityKeyMismatchException extends Exception
{
    protected $message = 'Security Key Mismatch or Missing: Encryption passphrase is invalid.';

    /**
     * Report the exception and trigger system lockdown.
     */
    public function report()
    {
        app(\App\Services\FortressLockService::class)->triggerLock();
    }
}
