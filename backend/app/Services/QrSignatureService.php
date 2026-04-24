<?php

namespace App\Services;

class QrSignatureService
{
    /**
     * Generate HMAC signature for QR code payload
     */
    public function generateSignature(array $payload): string
    {
        // Ensure consistent order of keys for hashing
        ksort($payload);
        $dataToSign = json_encode($payload);
        return hash_hmac('sha256', $dataToSign, config('app.key'));
    }

    /**
     * Validate HMAC signature
     */
    public function validateSignature(array $payload, string $signature): bool
    {
        // Extract the original payload without the signature for verification
        $payloadWithoutSignature = $payload;
        unset($payloadWithoutSignature['signature']);
        
        $expectedSignature = $this->generateSignature($payloadWithoutSignature);
        return hash_equals($expectedSignature, $signature);
    }
}
