<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

it('retries with a suffixed username when a concurrent create claims the username first', function (): void {
    $raceWasFaked = false;
    $concurrentRowRestored = false;
    $baseTransactionLevel = DB::transactionLevel();
    $createConcurrentUser = fn (): User => User::withoutEvents(
        fn (): User => User::factory()->create(['tenant_id' => null, 'username' => 'ops', 'email' => 'ops@elsewhere.test']),
    );

    User::creating(function (User $user) use (&$raceWasFaked, $createConcurrentUser): void {
        if ($raceWasFaked || $user->username !== 'ops') {
            return;
        }

        $raceWasFaked = true;
        $createConcurrentUser();
    });

    /*
    | A real concurrent row is committed by another connection and survives
    | this attempt's rollback; here it shares the connection, so it is put
    | back once the action's transaction has fully rolled back.
    */
    Event::listen(TransactionRolledBack::class, function () use (&$concurrentRowRestored, $baseTransactionLevel, $createConcurrentUser): void {
        if ($concurrentRowRestored || DB::transactionLevel() !== $baseTransactionLevel) {
            return;
        }

        $concurrentRowRestored = true;
        $createConcurrentUser();
    });

    $user = resolve(CreateConsoleUserAction::class)->execute('ops@vendra.test', 'a-secure-password');

    expect($raceWasFaked)->toBeTrue()
        ->and($user->username)->toBe('ops_2')
        ->and(User::query()->count())->toBe(2)
        ->and(ConsoleUser::query()->sole()->user_id)->toBe($user->getKey());
});

it('lets the unique guard reject an email a platform user already holds', function (): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);

    expect(fn (): User => resolve(CreateConsoleUserAction::class)->execute('ops@vendra.test', 'a-secure-password'))
        ->toThrow(QueryException::class)
        ->and(User::query()->count())->toBe(1)
        ->and(ConsoleUser::query()->count())->toBe(0);
});
