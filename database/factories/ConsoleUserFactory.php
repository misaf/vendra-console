<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\ConsoleUser;

/**
 * @extends Factory<ConsoleUser>
 */
final class ConsoleUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}
