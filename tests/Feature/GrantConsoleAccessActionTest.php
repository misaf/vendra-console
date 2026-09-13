<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

it('grants console access to an existing platform user once', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);

    expect(resolve(GrantConsoleAccessAction::class)->execute($user))->toBeTrue()
        ->and(resolve(GrantConsoleAccessAction::class)->execute($user))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(ConsoleUser::query()->count())->toBe(1);
});

it('refuses to grant console access to a tenant user', function (): void {
    $tenantUser = User::factory()->forTenant(createTestTenant())->create();

    expect(fn (): bool => resolve(GrantConsoleAccessAction::class)->execute($tenantUser))
        ->toThrow(InvalidArgumentException::class, 'belongs to a tenant')
        ->and(ConsoleUser::query()->count())->toBe(0);
});
