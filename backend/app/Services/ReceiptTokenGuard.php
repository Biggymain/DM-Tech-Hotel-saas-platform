<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class ReceiptTokenGuard
{
    /**
     * Generate an HMAC-SHA512 token for a payment transaction.
     * Combines invariant transaction details + APP_KEY.
     */
    public static function generateToken($id, $amount, $gatewayRef): string
    {
        $payload = "txn_{$id}_amt_{$amount}_ref_{$gatewayRef}";
        return hash_hmac('sha512', $payload, config('app.key'));
    }

    /**
     * Verify the cryptographic seal of a PaymentTransaction.
     */
    public static function verifyToken(PaymentTransaction $transaction): bool
    {
        if (empty($transaction->receipt_token)) {
            // Legacy/Unsealed transactions (or manual cash). We treat as passing
            // unless strict mode is enabled. For this implementation, we only 
            // fail if a token exists and is invalid, or if all Captured payments MUST have a token.
            // Since we just added this feature, legacy captured payments won't have it.
            // But we can check if it's captured and has a gateway transaction ID.
            if ($transaction->status === 'captured' && !empty($transaction->gateway_transaction_id) && $transaction->created_at >= now()->subDay()) {
                // If it's a new transaction created after enforcement, it MUST have a token.
                return false; 
            }
            return true;
        }

        $expected = self::generateToken(
            $transaction->id,
            $transaction->amount,
            $transaction->gateway_transaction_id ?? ''
        );

        return hash_equals($expected, $transaction->receipt_token);
    }
}
