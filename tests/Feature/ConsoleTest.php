<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('creates an active console for a fresh tenantless user', function (): void {
    $console = Console::factory()->active()->create();

    expect($console->user->tenant_id)->toBeNull()
        ->and($console->user->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('links a console to its canonical user', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);

    $console = Console::factory()->active()->for($user)->create();

    expect($console->user->is($user))->toBeTrue();
});

it('creates an inactive console when no state is given', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);

    $console = Console::query()->create(['user_id' => $user->getKey()]);

    expect($console->refresh()->active)->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('scopes consoles to the given user', function (): void {
    $grantedUser = User::factory()->create(['tenant_id' => null]);
    $otherUser = User::factory()->create(['tenant_id' => null]);
    Console::factory()->active()->for($grantedUser)->create();

    expect(Console::query()->forUser($grantedUser)->exists())->toBeTrue()
        ->and(Console::query()->forUser($otherUser)->exists())->toBeFalse();
});

it('scopes consoles to active or inactive ones', function (): void {
    $activeConsole = Console::factory()->active()->create();
    $inactiveConsole = Console::factory()->inactive()->create();

    expect(Console::query()->active()->sole()->is($activeConsole))->toBeTrue()
        ->and(Console::query()->inactive()->sole()->is($inactiveConsole))->toBeTrue();
});

it('denies console panel access to an inactive console while keeping the user', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);
    $console = Console::factory()->active()->for($user)->create();

    expect($user->canAccessPanel(Filament::getPanel('console')))->toBeTrue();

    $console->update(['active' => false]);

    expect($user->canAccessPanel(Filament::getPanel('console')))->toBeFalse()
        ->and(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});
