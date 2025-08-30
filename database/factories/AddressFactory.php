<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'address' => $this->faker->address(),
            'updated_at' => now(),
            'lat' => 40.621997,
            'lng' => -73.938831,
            'user_id' => $this->faker->numberBetween(4, 5),
        ];
    }
}
