<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('deactivates the console while keeping the canonical user', function (): void {
    $revokedConsole = Console::factory()->active()->create();
    Console::factory()->active()->create();

    expect(resolve(RevokeConsoleUserAction::class)->execute($revokedConsole->user))->toBeTrue()
        ->and($revokedConsole->refresh()->active)->toBeFalse()
        ->and($revokedConsole->user->canAccessPanel(Filament::getPanel('console')))->toBeFalse()
        ->and(User::query()->whereKey($revokedConsole->user_id)->exists())->toBeTrue()
        ->and(Console::query()->active()->count())->toBe(1);
});

it('refuses to revoke the last console user', function (): void {
    $lastUser = Console::factory()->active()->create()->user;

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class, "[{$lastUser->email}] is the last console user.")
        ->and($lastUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('refuses to revoke the last active console user when others are inactive', function (): void {
    $lastUser = Console::factory()->active()->create()->user;
    Console::factory()->inactive()->create();

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class);
});

it('reports nothing revoked for a user without console access', function (): void {
    Console::factory()->active()->create();
    $userWithoutAccess = User::factory()->create(['tenant_id' => null]);

    expect(resolve(RevokeConsoleUserAction::class)->execute($userWithoutAccess))->toBeFalse()
        ->and(Console::query()->active()->count())->toBe(1);
});

it('does not count a deleted user as another console user', function (): void {
    $lastUser = Console::factory()->active()->create()->user;
    Console::factory()->active()->create()->user->delete();

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class);
});

it('revokes a deleted user even as the last console user, keeping access disabled after restore', function (int $otherConsoleCount): void {
    $console = Console::factory()->active()->create();
    $user = $console->user;
    Console::factory()->active()->count($otherConsoleCount)->create();
    $user->fresh()->delete();

    expect(resolve(RevokeConsoleUserAction::class)->execute($user))->toBeTrue()
        ->and($console->refresh()->active)->toBeFalse();

    $user->refresh()->restore();

    expect($user->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
})->with([0, 1]);

it('reports nothing revoked for an inactive console grant', function (): void {
    $console = Console::factory()->inactive()->create();

    expect(resolve(RevokeConsoleUserAction::class)->execute($console->user))->toBeFalse()
        ->and($console->refresh()->active)->toBeFalse();
});
