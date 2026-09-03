<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OtaChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $channels = [
            [
                'name' => 'Booking.com',
                'provider' => 'booking_com',
                'api_endpoint' => 'https://api.booking.com/v1',
                'is_active' => true,
            ],
            [
                'name' => 'Expedia',
                'provider' => 'expedia',
                'api_endpoint' => 'https://api.expediapartnercentral.com/v2',
                'is_active' => true,
            ],
            [
                'name' => 'Airbnb',
                'provider' => 'airbnb',
                'api_endpoint' => 'https://api.airbnb.com/v2',
                'is_active' => true,
            ],
        ];

        foreach ($channels as $channel) {
            \App\Models\OtaChannel::updateOrCreate(
                ['provider' => $channel['provider']],
                $channel
            );
        }
    }
}
