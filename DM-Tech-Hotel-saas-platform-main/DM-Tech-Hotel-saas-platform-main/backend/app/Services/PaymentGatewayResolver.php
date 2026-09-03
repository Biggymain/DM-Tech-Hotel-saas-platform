<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\PaymentGateway;

/**
 * PaymentGatewayResolver
 *
 * Resolves the correct payment gateway credentials for a given hotel branch.
 *
 * Priority:
 *  1. Branch-specific PaymentGateway record in the DB (hotel_id set, is_active = true)
 *  2. Group-level fallback keys stored on HotelGroup
 *  3. Platform-level env keys as last resort
 */
class PaymentGatewayResolver
{
    /**
     * Resolve payment gateway config for use in a charge/reservation flow.
     *
     * @param Hotel $hotel   The branch hotel
     * @param string $gateway 'paystack' | 'flutterwave'
     */
    public function resolve(Hotel $hotel, string $gateway = 'paystack'): array
    {
        // 1. Branch-level gateway
        $branchGateway = PaymentGateway::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('gateway_name', $gateway)
            ->where('is_active', true)
            ->first();

        if ($branchGateway) {
            return [
                'source'      => 'branch',
                'gateway'     => $gateway,
                'public_key'  => $branchGateway->api_key,
                'secret_key'  => $branchGateway->api_secret,
                'webhook_key' => $branchGateway->webhook_secret,
            ];
        }

        // 2. Group-level fallback
        if ($hotel->hotel_group_id) {
            $group = HotelGroup::find($hotel->hotel_group_id);

            if ($group && $group->{"{$gateway}_secret_key"}) {
                return [
                    'source'     => 'group',
                    'gateway'    => $gateway,
                    'public_key' => $group->{"{$gateway}_public_key"},
                    'secret_key' => $group->{"{$gateway}_secret_key"},
                ];
            }
        }

        // 3. Platform-level env fallback
        return [
            'source'     => 'platform',
            'gateway'    => $gateway,
            'public_key' => config("payment.{$gateway}.public_key", env('PAYSTACK_PUBLIC_KEY')),
            'secret_key' => config("payment.{$gateway}.secret_key", env('PAYSTACK_SECRET_KEY')),
        ];
    }

    /**
     * Returns only the public/safe config for the frontend (never expose secret keys).
     */
    public function publicConfig(Hotel $hotel, string $gateway = 'paystack'): array
    {
        $config = $this->resolve($hotel, $gateway);
        return [
            'gateway'    => $config['gateway'],
            'public_key' => $config['public_key'],
            'source'     => $config['source'],
        ];
    }
}
