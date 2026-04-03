<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\PaymentGateway;
use App\Models\Reservation;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\Gateways\PayPalGateway;
use App\Services\Payments\Gateways\PaymentGatewayInterface;
use App\Events\PaymentInitiated;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use InvalidArgumentException;
use Exception;

class PaymentService
{
    protected TransactionContextService $contextService;

    public function __construct(TransactionContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    /**
     * Resolve the active gateway for a hotel.
     */
    public function resolveGateway(int $hotelId, string $gatewayName): PaymentGatewayInterface
    {
        $gatewayConfig = PaymentGateway::where('hotel_id', $hotelId)
            ->where('gateway_name', $gatewayName)
            ->where('is_active', true)
            ->first();

        if (!$gatewayConfig) {
            throw new InvalidArgumentException("Payment gateway '{$gatewayName}' is not configured or active for this hotel.");
        }

        return match ($gatewayName) {
            'stripe' => new StripeGateway($gatewayConfig->api_key, $gatewayConfig->api_secret),
            'paypal' => new PayPalGateway($gatewayConfig->api_key, $gatewayConfig->api_secret),
            'monnify' => new \App\Services\Payments\Gateways\MonnifyGateway($gatewayConfig->api_key, $gatewayConfig->api_secret, $gatewayConfig->contract_code),
            'flutterwave' => new \App\Services\Payments\Gateways\FlutterwaveGateway($gatewayConfig->api_key, $gatewayConfig->api_secret),
            default => throw new InvalidArgumentException("Unsupported gateway: {$gatewayName}")
        };
    }

    /**
     * Create a payment intent and log the transaction.
     */
    public function createPaymentIntent(
        float $amount, 
        string $currency, 
        string $gatewayName, 
        int $hotelId, 
        ?Reservation $reservation = null, 
        ?int $folioId = null,
        bool $isManual = false,
        array $posMetadata = [],
        string $paymentSource = 'guest_portal'
    ): array {
        \Illuminate\Support\Facades\Log::info("createPaymentIntent: hotelId: {$hotelId}, gatewayName: {$gatewayName}");
        $gatewayConfig = PaymentGateway::where('hotel_id', $hotelId)
            ->where('gateway_name', $gatewayName)
            ->where('is_active', true)
            ->first();

        if (!$gatewayConfig) {
            \Illuminate\Support\Facades\Log::info("createPaymentIntent: gatewayConfig is null for hotelId: {$hotelId}");
            throw new InvalidArgumentException("Payment gateway '{$gatewayName}' is not configured or active for this hotel.");
        }

        if ($isManual && $gatewayConfig->payment_mode === 'online') {
            throw new InvalidArgumentException("Gateway '{$gatewayName}' only supports online payments.");
        }

        if (!$isManual && $gatewayConfig->payment_mode === 'manual') {
            throw new InvalidArgumentException("Gateway '{$gatewayName}' only supports manual payments.");
        }

        $context = $this->contextService->captureContext();
        if ($reservation) {
            $context['reservation_id'] = $reservation->id;
            $context['guest_id'] = $reservation->guest_id;
        }

        if ($isManual && !empty($posMetadata)) {
            $context['pos_metadata'] = $posMetadata;
        }

        try {
            if ($isManual) {
                $transaction = PaymentTransaction::create([
                    'hotel_id' => $hotelId,
                    'reservation_id' => $reservation ? $reservation->id : null,
                    'guest_id' => $reservation ? $reservation->guest_id : null,
                    'folio_id' => $folioId,
                    'payment_gateway' => $gatewayName,
                    'gateway_transaction_id' => 'MANUAL_' . uniqid(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'manual_pending',
                    'payment_source' => $paymentSource,
                    'context_metadata' => $context,
                ]);

                event(new PaymentInitiated($transaction));

                return [
                    'transaction_id' => $transaction->id,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'status' => 'manual_pending'
                ];
            } else {
                $gateway = $this->resolveGateway($hotelId, $gatewayName);
                $intentResponse = $gateway->createPaymentIntent($amount, $currency);
                
                $transaction = PaymentTransaction::create([
                    'hotel_id' => $hotelId,
                    'reservation_id' => $reservation ? $reservation->id : null,
                    'guest_id' => $reservation ? $reservation->guest_id : null,
                    'folio_id' => $folioId,
                    'payment_gateway' => $gatewayName,
                    'gateway_transaction_id' => $intentResponse['gateway_transaction_id'],
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'authorized', // Initially authorized/pending
                    'payment_source' => $paymentSource,
                    'context_metadata' => $context,
                ]);

                event(new PaymentInitiated($transaction));

                return [
                    'transaction_id' => $transaction->id,
                    'client_secret' => $intentResponse['client_secret'] ?? null,
                    'gateway_transaction_id' => $intentResponse['gateway_transaction_id']
                ];
            }
        } catch (Exception $e) {
            // If creation fails, we might create a failed transaction record here, but usually it throws before creation.
            throw $e;
        }
    }

    /**
     * Capture a previously authorized payment.
     */
    public function capturePayment(PaymentTransaction $transaction): PaymentTransaction
    {
        $gateway = $this->resolveGateway($transaction->hotel_id, $transaction->payment_gateway);

        try {
            $response = $gateway->capturePayment($transaction->gateway_transaction_id);
            
            $transaction->update([
                'status' => 'captured',
                'processed_at' => now()
            ]);

            event(new PaymentCompleted($transaction));

            return $transaction;
        } catch (Exception $e) {
            $transaction->update([
                'status' => 'failed',
                'processed_at' => now()
            ]);
            
            $context = $transaction->context_metadata;
            $context['error'] = $e->getMessage();
            $transaction->update(['context_metadata' => $context]);

            event(new PaymentFailed($transaction));
            throw $e;
        }
    }

    /**
     * Refund a captured payment.
     */
    public function refundPayment(PaymentTransaction $transaction, ?float $amount = null): PaymentTransaction
    {
        if ($transaction->status !== 'captured') {
            throw new InvalidArgumentException("Only captured payments can be refunded.");
        }

        $gateway = $this->resolveGateway($transaction->hotel_id, $transaction->payment_gateway);
        $refundAmount = $amount ?? $transaction->amount;

        try {
            $response = $gateway->refundPayment($transaction->gateway_transaction_id, $refundAmount);
            
            // For simplicity, we mark the whole transaction as refunded here.
            // If partial, could be different status.
            $transaction->update([
                'status' => 'refunded',
                'processed_at' => now()
            ]);

            event(new PaymentRefunded($transaction, $refundAmount));

            return $transaction;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
