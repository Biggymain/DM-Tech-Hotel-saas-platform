<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait HandlesWebhookSignatures
{
    /**
     * Verify the Monnify HMAC signature
     */
    protected function verifyMonnifySignature(string $payload, string $secret, string $headerSignature): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, $secret);
        return hash_equals($computedSignature, $headerSignature);
    }

    /**
     * Verify the Paystack HMAC signature
     */
    protected function verifyPaystackSignature(string $payload, string $secret, string $headerSignature): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, $secret);
        return hash_equals($computedSignature, $headerSignature);
    }

    /**
     * Verify the Flutterwave secret hash signature
     */
    protected function verifyFlutterwaveSignature(string $secret, string $headerSignature): bool
    {
        return hash_equals($secret, $headerSignature);
    }
}
