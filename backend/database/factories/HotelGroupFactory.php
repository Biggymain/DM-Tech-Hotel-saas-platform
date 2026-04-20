<?php

namespace Database\Factories;

use App\Models\HotelGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HotelGroupFactory extends Factory
{
    protected $model = HotelGroup::class;

    public function definition(): array
    {
        $name = $this->faker->company() . ' Hotels Group';

        return [
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'contact_email' => $this->faker->companyEmail(),
            'country'       => 'Nigeria',
            'currency'      => 'NGN',
            'tax_rate'      => 7.50,
            'is_active'     => true,
            'is_licensed'   => true,
        ];
    }
}
