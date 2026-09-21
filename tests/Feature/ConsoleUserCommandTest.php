<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Console\Commands\ConsoleUserCommand;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;

function printedConsolePassword(string $output): string
{
    return Str::of($output)->match('/\|\s*https:\/\/[^|]+\|\s*[^|]+\|\s*(\S+)\s*\|/')->toString();
}

function grantConsoleAccess(User $user): void
{
    Console::factory()->active()->for($user)->create();
}

it('constructs the command without resolving its actions', function (): void {
    foreach ([CreateConsoleUserAction::class, GrantConsoleAccessAction::class, RevokeConsoleUserAction::class, UpdateUserPasswordAction::class] as $action) {
        $this->app->bind($action, fn (): never => throw new LogicException('An action was resolved before it was needed.'));
    }

    expect(resolve(ConsoleUserCommand::class))->toBeInstanceOf(ConsoleUserCommand::class);
});

it('creates a console user with a generated password and prints it', function (): void {
    Config::set('app.url', 'https://vendra.test');

    expect(Artisan::call('vendra-console:user', ['--username' => 'chosen_name']))->toBe(0);

    $output = Artisan::output();
    $consoleUser = User::query()->sole();

    expect($output)->toContain('console@vendra.test')
        ->and($consoleUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check(printedConsolePassword($output), $consoleUser->password))->toBeTrue();
});

it('issues a new password to an existing console user without creating another', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user', ['--email' => 'OPS@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('the-new-password')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(1)
        ->and(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('trims and lowercases the given email before creating a console user', function (): void {
    $this->artisan('vendra-console:user', ['--username' => ' chosen_name ', '--email' => ' OPS@Vendra.test ', '--password' => 'the-new-password'])
        ->expectsOutputToContain('ops@vendra.test')
        ->assertSuccessful();

    expect(User::query()->sole()->email)->toBe('ops@vendra.test')
        ->and(User::query()->sole()->username)->toBe('chosen_name');
});

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

    $this->artisan('vendra-console:user', ['--email' => $consoleUser->email, '--password' => 'the-new-password', '--no-interaction' => true])
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue()
        ->and($tenantUser->refresh()->password)->toBe($tenantPassword)
        ->and(Console::query()->forUser($tenantUser)->exists())->toBeFalse();
})->with(['current tenant' => false, 'authenticated tenant user' => true]);

it('revokes tenantless console access inside a tenant context', function (bool $authenticatedTenantUser): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();
    makeCurrentTestTenant();
    $tenantUser = User::factory()->create(['email' => $consoleUser->email]);

    if ($authenticatedTenantUser) {
        forgetCurrentTestTenant();
        $this->actingAs($tenantUser);
    }

    $this->artisan('vendra-console:user', ['--email' => $consoleUser->email, '--revoke' => true])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeFalse()
        ->and(Console::query()->active()->count())->toBe(1);
})->with(['current tenant' => false, 'authenticated tenant user' => true]);

it('does not find a soft-deleted tenantless user when revoking console access', function (): void {
    $consoleUser = User::factory()->trashed()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    makeCurrentTestTenant();

    $this->artisan('vendra-console:user', ['--email' => $consoleUser->email, '--revoke' => true])
        ->expectsOutputToContain('No tenantless user has the email [ops@vendra.test].')
        ->assertFailed();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeTrue();
});

it('rejects an invalid email without creating a console user', function (): void {
    $this->artisan('vendra-console:user', ['--email' => 'not-an-email'])
        ->expectsOutputToContain('valid email')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('rejects a password that fails the password rules without creating a console user', function (string $password): void {
    $this->artisan('vendra-console:user', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test', '--password' => $password])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
})->with(['too short' => 'short', 'empty' => '', 'whitespace' => '        ']);

it('keeps an existing console user password when the given password fails the password rules', function (string $password): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user', ['--email' => 'ops@vendra.test', '--password' => $password, '--no-interaction' => true])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
})->with(['too short' => 'short', 'empty' => '', 'whitespace' => '        ']);

it('generates a password that satisfies the application password rules', function (): void {
    $passwordDefaults = Password::$defaultCallback;
    Password::defaults(fn () => Password::min(40));

    try {
        expect(Artisan::call('vendra-console:user', ['--username' => 'chosen_name']))->toBe(0);

        $password = printedConsolePassword(Artisan::output());

        expect($password)->toHaveLength(40)
            ->and(Hash::check($password, User::query()->sole()->password))->toBeTrue();
    } finally {
        Password::$defaultCallback = $passwordDefaults;
    }
});

it('falls back to localhost for the email and console url when the app url has no host', function (): void {
    Config::set('app.url', '');

    expect(Artisan::call('vendra-console:user', ['--username' => 'chosen_name']))->toBe(0)
        ->and(Artisan::output())->toContain('console@localhost')
        ->toContain('https://console.localhost');
});

it('rejects a username another tenantless user already holds', function (): void {
    User::factory()->create(['tenant_id' => null, 'username' => 'operations_1', 'email' => 'operations_1@a.test']);

    $this->artisan('vendra-console:user', ['--username' => 'operations_1', '--email' => 'operations_1@b.test'])
        ->expectsOutputToContain('username has already been taken')
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(0);
});

it('does not grant console access to an existing user when the prompt is declined', function (): void {
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'reseller@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);

    $this->artisan('vendra-console:user', ['--email' => 'reseller@vendra.test'])
        ->expectsConfirmation('[reseller@vendra.test] is an existing user without console access. Grant console access and issue a new password?', 'no')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0)
        ->and(Hash::check('the-old-password', $user->refresh()->password))->toBeTrue();
});

it('grants console access to an existing user once the prompt is confirmed', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'reseller@vendra.test']);

    $this->artisan('vendra-console:user', ['--email' => 'reseller@vendra.test', '--password' => 'the-new-password'])
        ->expectsConfirmation('[reseller@vendra.test] is an existing user without console access. Grant console access and issue a new password?', 'yes')
        ->expectsOutputToContain('Console access granted and password updated.')
        ->assertSuccessful();

    expect($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check('the-new-password', $user->password))->toBeTrue();
});

it('revokes console access from the user given by email', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($revokedUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user', ['--email' => 'OPS@vendra.test', '--revoke' => true])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('refuses to revoke the last console user from the command', function (): void {
    $lastUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($lastUser);

    $this->artisan('vendra-console:user', ['--email' => 'ops@vendra.test', '--revoke' => true])
        ->expectsOutputToContain('[ops@vendra.test] is the last console user.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(1);
});

it('requires an email or a username to revoke console access', function (): void {
    Console::factory()->active()->count(2)->create();

    $this->artisan('vendra-console:user', ['--revoke' => true])
        ->expectsOutputToContain('The --revoke option requires --email or --username.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(2);
});

it('revokes console access from the user given by username', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    grantConsoleAccess($revokedUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user', ['--username' => ' chosen_name ', '--revoke' => true])
        ->expectsOutputToContain("Console access revoked from [{$revokedUser->email}].")
        ->assertSuccessful();

    expect($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('reports an unknown username when revoking console access', function (): void {
    Console::factory()->active()->count(2)->create();

    $this->artisan('vendra-console:user', ['--username' => 'no_such_user', '--revoke' => true])
        ->expectsOutputToContain('No tenantless user has the username [no_such_user].')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
});

it('refuses to revoke when an email and a username are both given', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'chosen_name']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user', ['--email' => 'ops@vendra.test', '--username' => 'chosen_name', '--revoke' => true])
        ->expectsOutputToContain('Pass either --email or --username to --revoke, not both.')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
});

it('asks before granting console access when the default email belongs to an existing user', function (): void {
    Config::set('app.url', 'https://vendra.test');
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'console@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);

    $this->artisan('vendra-console:user')
        ->expectsConfirmation('[console@vendra.test] is an existing user without console access. Grant console access and issue a new password?', 'no')
        ->expectsOutputToContain('No console access was granted.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0)
        ->and(Hash::check('the-old-password', $user->refresh()->password))->toBeTrue();
});

it('keeps a console user password when the generated reset is declined', function (): void {
    Config::set('app.url', 'https://vendra.test');
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'console@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user')
        ->expectsConfirmation('[console@vendra.test] is already a console user. Issue a new password?', 'no')
        ->expectsOutputToContain('The password was not changed.')
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

    $this->artisan('vendra-console:user')
        ->expectsConfirmation('[console@vendra.test] is already a console user. Issue a new password?', 'yes')
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});

it('prompts for a username when creating a console user interactively', function (): void {
    $this->artisan('vendra-console:user', ['--email' => 'ops@vendra.test'])
        ->expectsQuestion('Username', 'chosen_name')
        ->assertSuccessful();

    expect(User::query()->sole()->username)->toBe('chosen_name');
});

it('requires an explicit username when creating a console user without interaction', function (): void {
    $this->artisan('vendra-console:user', ['--no-interaction' => true])
        ->expectsOutputToContain('A username is required')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('rejects an invalid username without creating a user', function (string $username): void {
    $this->artisan('vendra-console:user', ['--username' => $username])
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
})->with([
    'blank' => '   ',
    'too short' => 'ab',
    'too long' => str_repeat('a', 13),
    'spaces' => 'user name',
    'punctuation' => 'user.name',
]);

it('accepts usernames at the allowed length boundaries', function (string $username): void {
    $this->artisan('vendra-console:user', ['--username' => $username])
        ->assertSuccessful();

    expect(User::query()->sole()->username)->toBe($username);
})->with(['minimum' => 'a_1', 'maximum' => 'user-name_12']);

it('allows a username held by a tenant user or a soft-deleted tenantless user', function (): void {
    User::factory()->trashed()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    makeCurrentTestTenant();
    User::factory()->create(['username' => 'chosen_name']);
    forgetCurrentTestTenant();

    $this->artisan('vendra-console:user', ['--username' => 'chosen_name'])
        ->assertSuccessful();

    expect(User::query()->whereNull('tenant_id')->sole()->username)->toBe('chosen_name');
});

it('grants console access to an existing user without a prompt when forced', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'reseller@vendra.test']);

    $this->artisan('vendra-console:user', [
        '--email' => 'reseller@vendra.test',
        '--password' => 'the-new-password',
        '--force' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('Console access granted and password updated.')
        ->assertSuccessful();

    expect($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check('the-new-password', $user->password))->toBeTrue();
});

it('issues a new password to a console user without a prompt when forced', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user', ['--email' => 'ops@vendra.test', '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});

it('rejects a blank email instead of falling back to the default console address', function (string $email): void {
    Config::set('app.url', 'https://vendra.test');

    $this->artisan('vendra-console:user', ['--username' => 'chosen_name', '--email' => $email])
        ->expectsOutputToContain('The --email option cannot be blank.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
})->with(['empty' => '', 'whitespace' => '   ']);

it('requires a non-blank identifier to revoke console access', function (string $option): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user', [$option => '   ', '--revoke' => true])
        ->expectsOutputToContain('The --revoke option requires --email or --username.')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
})->with(['email' => '--email', 'username' => '--username']);
