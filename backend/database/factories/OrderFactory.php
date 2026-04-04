<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'outlet_id' => Outlet::factory(),
            'department_id' => \App\Models\Department::factory(),
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'order_source' => 'pos',
            'status' => 'pending',
            'order_status' => 'pending',
            'total_amount' => $this->faker->randomFloat(2, 1000, 50000),
            'created_by' => User::factory(),
            'waiter_id' => User::factory(),
        ];
    }
}
