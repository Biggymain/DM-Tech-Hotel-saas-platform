<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => $this->faker->word() . ' Dept',
        ];
    }
}
