<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('deactivates the console while keeping the canonical user', function (): void {
    $revokedConsole = Console::factory()->create();
    Console::factory()->create();

    expect(resolve(RevokeConsoleUserAction::class)->execute($revokedConsole->user))->toBeTrue()
        ->and($revokedConsole->refresh()->active)->toBeFalse()
        ->and($revokedConsole->user->canAccessPanel(Filament::getPanel('console')))->toBeFalse()
        ->and(User::query()->whereKey($revokedConsole->user_id)->exists())->toBeTrue()
        ->and(Console::query()->active()->count())->toBe(1);
});

it('refuses to revoke the last console user', function (): void {
    $lastUser = Console::factory()->create()->user;

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class, "[{$lastUser->email}] is the last console user.")
        ->and($lastUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('refuses to revoke the last active console user when others are inactive', function (): void {
    $lastUser = Console::factory()->create()->user;
    Console::factory()->inactive()->create();

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class);
});

it('reports nothing revoked for a user without console access', function (): void {
    Console::factory()->create();
    $userWithoutAccess = User::factory()->create(['tenant_id' => null]);

    expect(resolve(RevokeConsoleUserAction::class)->execute($userWithoutAccess))->toBeFalse()
        ->and(Console::query()->active()->count())->toBe(1);
});
