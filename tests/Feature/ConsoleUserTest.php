<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

it('creates a console grant for a fresh platform user by default', function (): void {
    $consoleUser = ConsoleUser::factory()->create();

    expect($consoleUser->user->tenant_id)->toBeNull()
        ->and($consoleUser->user->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('links a console grant to its canonical user', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);

    $consoleUser = ConsoleUser::factory()->for($user)->create();

    expect($consoleUser->user->is($user))->toBeTrue();
});

it('scopes grants to the given user', function (): void {
    $grantedUser = User::factory()->create(['tenant_id' => null]);
    $otherUser = User::factory()->create(['tenant_id' => null]);
    ConsoleUser::factory()->for($grantedUser)->create();

    expect(ConsoleUser::query()->forUser($grantedUser)->exists())->toBeTrue()
        ->and(ConsoleUser::query()->forUser($otherUser)->exists())->toBeFalse();
});

it('revokes console panel access when the grant is deleted while keeping the user', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);
    $consoleUser = ConsoleUser::factory()->for($user)->create();

    expect($user->canAccessPanel(Filament::getPanel('console')))->toBeTrue();

    $consoleUser->delete();

    expect($user->canAccessPanel(Filament::getPanel('console')))->toBeFalse()
        ->and(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});
