<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Console\Commands\IssueConsolePasswordCommand;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;

it('constructs the command without resolving its actions', function (): void {
    $this->app->bind(UpdateUserPasswordAction::class, fn (): never => throw new LogicException('An action was resolved before it was needed.'));

    expect(resolve(IssueConsolePasswordCommand::class))->toBeInstanceOf(IssueConsolePasswordCommand::class);
});

it('issues a new password to an existing console user without creating another', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'OPS@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('the-new-password')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(1)
        ->and(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('resets a console user password by username with or without their email', function (bool $includeEmail): void {
    Config::set('app.url', 'https://vendra.test');
    $consoleUser = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name', 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    $defaultUser = User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test']);
    grantConsoleAccess($defaultUser);
    $defaultPassword = $defaultUser->password;
    $options = ['--username' => ' chosen_name ', '--password' => 'the-new-password', '--no-interaction' => true];

    if ($includeEmail) {
        $options['--email'] = ' OPS@VENDRA.TEST ';
    }

    $this->artisan('vendra-console:user-password', $options)
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue()
        ->and($defaultUser->refresh()->password)->toBe($defaultPassword)
        ->and(User::query()->count())->toBe(2);
})->with(['username only' => false, 'both identifiers' => true]);

it('resets a console user password by username regardless of the default email', function (string $defaultEmail): void {
    Config::set('vendra-console.default_email', $defaultEmail);
    $consoleUser = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--username' => 'chosen_name', '--password' => 'the-new-password', '--no-interaction' => true])
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
})->with(['blank' => '', 'invalid' => 'console@localhost']);

it('does not reset passwords when email and username identify different users', function (): void {
    $emailUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    $usernameUser = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    grantConsoleAccess($emailUser);
    grantConsoleAccess($usernameUser);
    $emailPassword = $emailUser->password;
    $usernamePassword = $usernameUser->password;

    $this->artisan('vendra-console:user-password', ['--email' => $emailUser->email, '--username' => $usernameUser->username, '--password' => 'the-new-password'])
        ->expectsOutputToContain('The --email and --username options identify different users.')
        ->assertFailed();

    expect($emailUser->refresh()->password)->toBe($emailPassword)
        ->and($usernameUser->refresh()->password)->toBe($usernamePassword)
        ->and(User::query()->count())->toBe(2);
});

it('names the identifier that matches no user when the other matches', function (string $matchingIdentifier, string $expectedMessage): void {
    $user = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name', 'email' => 'ops@vendra.test']);
    grantConsoleAccess($user);
    $password = $user->password;

    $this->artisan('vendra-console:user-password', [
        '--email' => $matchingIdentifier === 'email' ? $user->email : 'missing@vendra.test',
        '--username' => $matchingIdentifier === 'username' ? $user->username : 'missing_user',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain($expectedMessage)
        ->doesntExpectOutputToContain('identify different users')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($password)
        ->and(Console::query()->active()->forUser($user)->exists())->toBeTrue();
    $this->assertDatabaseCount('users', 1);
})->with([
    'unknown username' => ['email', 'No tenantless user has the username [missing_user].'],
    'unknown email' => ['username', 'No tenantless user has the email [missing@vendra.test].'],
]);

it('resets only the tenantless user password inside a tenant context', function (bool $authenticatedTenantUser): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    makeCurrentTestTenant();
    $tenantUser = User::factory()->create(['email' => $consoleUser->email]);
    $tenantPassword = $tenantUser->password;

    if ($authenticatedTenantUser) {
        forgetCurrentTestTenant();
        $this->actingAs($tenantUser);
    }

    $this->artisan('vendra-console:user-password', ['--email' => $consoleUser->email, '--password' => 'the-new-password', '--no-interaction' => true])
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue()
        ->and($tenantUser->refresh()->password)->toBe($tenantPassword)
        ->and(Console::query()->forUser($tenantUser)->exists())->toBeFalse();
})->with(['current tenant' => false, 'authenticated tenant user' => true]);

it('keeps an existing console user password when the given password fails the password rules', function (string $password): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--password' => $password, '--no-interaction' => true])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
})->with(['too short' => 'short', 'empty' => '', 'whitespace' => '        ']);

it('keeps a console user password when the generated reset is declined', function (): void {
    Config::set('app.url', 'https://vendra.test');
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'console@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'console@vendra.test'])
        ->expectsConfirmation('[console@vendra.test] is already a console user. Issue a new password?', 'no')
        ->expectsOutputToContain('The password was not changed.')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('points at --force when a generated reset cannot be confirmed without interaction', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--no-interaction' => true])
        ->expectsOutputToContain('The password was not changed. Pass --force, or give --password, to issue a password without a prompt.')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('issues a generated password to a console user once the reset is confirmed', function (): void {
    Config::set('app.url', 'https://vendra.test');
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'console@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'console@vendra.test'])
        ->expectsConfirmation('[console@vendra.test] is already a console user. Issue a new password?', 'yes')
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});

it('issues a new password to a console user without a prompt when forced', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});

it('points at the create command when no tenantless user matches', function (): void {
    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('No tenantless user has the email [ops@vendra.test]. Use vendra-console:user-create to create one.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('points at the grant command when the user has no console access', function (): void {
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'reseller@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    Console::factory()->inactive()->for($user)->create();

    $this->artisan('vendra-console:user-password', ['--email' => 'reseller@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('[reseller@vendra.test] has no console access. Use vendra-console:user-grant to grant it.')
        ->assertFailed();

    expect(Hash::check('the-old-password', $user->refresh()->password))->toBeTrue();
});

it('rejects a blank identifier instead of falling back to the default console address', function (string $option, string $value): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test']);
    grantConsoleAccess($consoleUser);
    $password = $consoleUser->password;

    $this->artisan('vendra-console:user-password', ['--'.$option => $value, '--password' => 'the-new-password'])
        ->expectsOutputToContain("The --{$option} option cannot be blank.")
        ->assertFailed();

    expect($consoleUser->refresh()->password)->toBe($password);
})->with([
    'empty email' => ['email', ''],
    'whitespace email' => ['email', '   '],
    'empty username' => ['username', ''],
    'whitespace username' => ['username', '   '],
]);

it('asks for the new password without echo and without a confirmation when --password is given no value', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--password' => null])
        ->expectsQuestion('Password', 'the-new-password')
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('falls back to the default email without interaction when no identifier is given', function (): void {
    Config::set('vendra-console.default_email', 'console@vendra.test');
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test', 'password' => Hash::make('the-old-password')]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-password', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});

it('searches console users when an interactive run names no user', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'ops_user']);
    grantConsoleAccess($consoleUser);
    User::factory()->create(['tenant_id' => null, 'email' => 'ops-plain@vendra.test', 'username' => 'ops_plain']);

    $this->artisan('vendra-console:user-password', ['--password' => 'the-new-password'])
        ->expectsSearch('Which console user should get a new password?', 'ops@vendra.test', 'ops', ['ops@vendra.test' => 'ops@vendra.test (ops_user)'])
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('fails instead of searching when no tenantless user has console access', function (): void {
    $user = User::factory()->create(['tenant_id' => null]);
    $password = $user->password;

    $this->artisan('vendra-console:user-password')
        ->expectsOutputToContain('No tenantless user has console access. Use vendra-console:user-create to create one.')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($password);
});

it('keeps the password when console access is revoked after the first access check', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'password' => Hash::make('the-old-password')]);
    $console = Console::factory()->active()->for($consoleUser)->create();
    $revoked = false;

    DB::listen(function (QueryExecuted $query) use ($console, &$revoked): void {
        if (! $revoked && str_contains($query->sql, 'consoles')) {
            $revoked = true;
            $console->update(['active' => false]);
        }
    });

    $this->artisan('vendra-console:user-password', ['--email' => 'ops@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('[ops@vendra.test] no longer has console access. The password was not changed.')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
});
