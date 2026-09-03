<?php

namespace App\Services\Payments\Gateways;

interface PaymentGatewayInterface
{
    public function createPaymentIntent(float $amount, string $currency, array $metadata = []): array;
    public function capturePayment(string $gatewayTransactionId): array;
    public function refundPayment(string $gatewayTransactionId, float $amount = null): array;
    public function verifyTransaction(string $gatewayTransactionId): array;
    public function handleWebhook(array $payload): array;
}
