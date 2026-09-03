<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'hotel_id'           => Hotel::factory(),
            'room_type_id'       => RoomType::factory(),
            'room_number'        => $this->faker->unique()->numerify('###'),
            'floor'              => (string) $this->faker->numberBetween(1, 10),
            'status'             => 'available',
            'housekeeping_status'=> 'clean',
            'maintenance_notes'  => null,
            'maintenance_until'  => null,
        ];
    }
}
