<?php

namespace App\Services\Payments\Gateways;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    protected $apiKey;
    protected $apiSecret;

    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    public function createPaymentIntent(float $amount, string $currency, array $metadata = []): array
    {
        // Mock Flutterwave intent creation
        return [
            'gateway_transaction_id' => 'FLW_' . uniqid(),
            'client_secret' => 'FLW_SEC_' . uniqid(),
            'status' => 'authorized'
        ];
    }

    public function capturePayment(string $gatewayTransactionId): array
    {
        // Mock Flutterwave capture
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function refundPayment(string $gatewayTransactionId, float $amount = null): array
    {
        // Mock Flutterwave refund
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'refunded'
        ];
    }

    public function verifyTransaction(string $gatewayTransactionId): array
    {
        // Mock Flutterwave verification
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function handleWebhook(array $payload): array
    {
        // Mock Flutterwave webhook parsing
        $txRef = $payload['data']['tx_ref'] ?? null;
        $status = $payload['data']['status'] === 'successful' ? 'captured' : 'failed';

        return [
            'gateway_transaction_id' => $txRef,
            'status' => $status
        ];
    }
}
