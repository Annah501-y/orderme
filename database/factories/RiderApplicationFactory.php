<?php

namespace Database\Factories;

use App\Models\RiderApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderApplication>
 */
class RiderApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '6'.fake()->numerify('########'),
            'user_id' => null,
            'status' => 'pending',
        ];
    }
}
