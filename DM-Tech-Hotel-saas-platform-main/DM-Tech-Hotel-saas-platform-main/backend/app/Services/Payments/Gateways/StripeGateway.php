<?php

namespace App\Services\Payments\Gateways;

class StripeGateway implements PaymentGatewayInterface
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
        // Mock Stripe intent creation
        return [
            'gateway_transaction_id' => 'pi_stripe_' . uniqid(),
            'client_secret' => 'secret_stripe_' . uniqid(),
            'status' => 'authorized'
        ];
    }

    public function capturePayment(string $gatewayTransactionId): array
    {
        // Mock Stripe capture
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function refundPayment(string $gatewayTransactionId, float $amount = null): array
    {
        // Mock Stripe refund
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'refunded'
        ];
    }

    public function verifyTransaction(string $gatewayTransactionId): array
    {
        // Mock verification
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function handleWebhook(array $payload): array
    {
        // Mock webhook handling
        return [
            'gateway_transaction_id' => $payload['data']['object']['id'] ?? null,
            'status' => 'captured'
        ];
    }
}
