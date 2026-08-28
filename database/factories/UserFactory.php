<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+255' . fake()->numerify('7########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password@123'),
            'remember_token' => Str::random(10),
        ];
    }
}