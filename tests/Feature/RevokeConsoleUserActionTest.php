<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

it('revokes console access while keeping the canonical user', function (): void {
    $revokedUser = ConsoleUser::factory()->create()->user;
    ConsoleUser::factory()->create();

    expect(resolve(RevokeConsoleUserAction::class)->execute($revokedUser))->toBeTrue()
        ->and($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse()
        ->and(User::query()->whereKey($revokedUser->getKey())->exists())->toBeTrue()
        ->and(ConsoleUser::query()->count())->toBe(1);
});

it('refuses to revoke the last console user', function (): void {
    $lastUser = ConsoleUser::factory()->create()->user;

    expect(fn (): bool => resolve(RevokeConsoleUserAction::class)->execute($lastUser))
        ->toThrow(LastConsoleUserException::class, "[{$lastUser->email}] is the last console user.")
        ->and($lastUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('reports nothing revoked for a user without console access', function (): void {
    ConsoleUser::factory()->create();
    $userWithoutAccess = User::factory()->create(['tenant_id' => null]);

    expect(resolve(RevokeConsoleUserAction::class)->execute($userWithoutAccess))->toBeFalse()
        ->and(ConsoleUser::query()->count())->toBe(1);
});
