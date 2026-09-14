<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Database\Factories\UserFactory;

/**
 * @extends Factory<ConsoleUser>
 */
#[UseModel(ConsoleUser::class)]
final class ConsoleUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new()->state(['tenant_id' => null]),
        ];
    }
}
