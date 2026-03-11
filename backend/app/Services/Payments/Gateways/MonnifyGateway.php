<?php

namespace App\Services\Payments\Gateways;

class MonnifyGateway implements PaymentGatewayInterface
{
    protected $apiKey;
    protected $apiSecret;
    protected $contractCode;

    public function __construct(string $apiKey, string $apiSecret, string $contractCode = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->contractCode = $contractCode;
    }

    public function createPaymentIntent(float $amount, string $currency, array $metadata = []): array
    {
        // Mock Monnify intent creation
        return [
            'gateway_transaction_id' => 'MNFY_' . uniqid(),
            'client_secret' => 'MNFY_SEC_' . uniqid(),
            'status' => 'authorized'
        ];
    }

    public function capturePayment(string $gatewayTransactionId): array
    {
        // Mock Monnify capture
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function refundPayment(string $gatewayTransactionId, float $amount = null): array
    {
        // Mock Monnify refund
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'refunded'
        ];
    }

    public function verifyTransaction(string $gatewayTransactionId): array
    {
        // Mock Monnify verification
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function handleWebhook(array $payload): array
    {
        // Mock Monnify webhook parsing
        $txRef = $payload['eventData']['transactionReference'] ?? null;
        $status = $payload['eventData']['paymentStatus'] === 'PAID' ? 'captured' : 'failed';

        return [
            'gateway_transaction_id' => $txRef,
            'status' => $status
        ];
    }
}
