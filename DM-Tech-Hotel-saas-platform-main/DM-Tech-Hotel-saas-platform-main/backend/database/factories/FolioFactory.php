<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class FolioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'reservation_id' => Reservation::factory(),
            'status' => 'open',
            'currency' => 'NGN',
            'total_charges' => 0,
            'total_payments' => 0,
            'balance' => 0,
        ];
    }
}
