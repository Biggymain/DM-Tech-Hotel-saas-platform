<?php

namespace App\Services\Payments\Gateways;

class PayPalGateway implements PaymentGatewayInterface
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
        // Mock PayPal intent creation
        return [
            'gateway_transaction_id' => 'pi_paypal_' . uniqid(),
            'client_secret' => 'secret_paypal_' . uniqid(),
            'status' => 'authorized'
        ];
    }

    public function capturePayment(string $gatewayTransactionId): array
    {
        // Mock PayPal capture
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function refundPayment(string $gatewayTransactionId, float $amount = null): array
    {
        // Mock PayPal refund
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'refunded'
        ];
    }

    public function verifyTransaction(string $gatewayTransactionId): array
    {
        return [
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'captured'
        ];
    }

    public function handleWebhook(array $payload): array
    {
        return [
            'gateway_transaction_id' => $payload['resource']['id'] ?? null,
            'status' => 'captured'
        ];
    }
}
