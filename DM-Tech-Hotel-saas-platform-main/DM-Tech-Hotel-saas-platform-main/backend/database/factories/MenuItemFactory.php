<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\MenuItem;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'outlet_id' => Outlet::factory(),
            'department_id' => Department::factory(),
            'name' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 500, 5000),
            'is_available' => true,
            'is_active' => true,
        ];
    }
}
