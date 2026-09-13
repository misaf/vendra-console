<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

it('creates a platform user with console access', function (): void {
    $user = resolve(CreateConsoleUserAction::class)->execute('ops@vendra.test', 'a-secure-password');

    expect($user->email)->toBe('ops@vendra.test')
        ->and($user->tenant_id)->toBeNull()
        ->and($user->username)->toBe('ops')
        ->and(Hash::check('a-secure-password', $user->password))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('lets the unique guard reject an email a platform user already holds', function (): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);

    expect(fn (): User => resolve(CreateConsoleUserAction::class)->execute('ops@vendra.test', 'a-secure-password'))
        ->toThrow(QueryException::class)
        ->and(User::query()->count())->toBe(1)
        ->and(ConsoleUser::query()->count())->toBe(0);
});
