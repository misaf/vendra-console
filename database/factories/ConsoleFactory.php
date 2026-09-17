<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Database\Factories\UserFactory;

/**
 * @extends Factory<Console>
 */
#[UseModel(Console::class)]
final class ConsoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new()->state(['tenant_id' => null]),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
