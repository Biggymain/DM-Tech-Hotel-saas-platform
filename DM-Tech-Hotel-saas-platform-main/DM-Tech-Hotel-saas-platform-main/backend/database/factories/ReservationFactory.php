<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'guest_id' => Guest::factory(),
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'adults' => 1,
            'children' => 0,
        ];
    }
}
