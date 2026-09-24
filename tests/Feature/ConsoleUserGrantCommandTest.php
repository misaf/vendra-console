<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Console\Commands\GrantConsoleAccessCommand;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('constructs the command without resolving its actions', function (): void {
    $this->app->bind(GrantConsoleAccessAction::class, fn (): never => throw new LogicException('An action was resolved before it was needed.'));

    expect(resolve(GrantConsoleAccessCommand::class))->toBeInstanceOf(GrantConsoleAccessCommand::class);
});

it('grants console access to an existing user and keeps their password', function (): void {
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'reseller@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);

    $this->artisan('vendra-console:user-grant', ['--email' => 'RESELLER@vendra.test'])
        ->expectsOutputToContain('Console access granted to [reseller@vendra.test].')
        ->assertSuccessful();

    expect($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check('the-old-password', $user->password))->toBeTrue();
});

it('grants console access and sets the given password', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name']);

    $this->artisan('vendra-console:user-grant', ['--username' => ' chosen_name ', '--password' => 'the-new-password'])
        ->expectsOutputToContain('Console access granted and password updated.')
        ->assertSuccessful();

    expect($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check('the-new-password', $user->password))->toBeTrue();
});

it('reactivates a revoked console without creating a second row', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    Console::factory()->inactive()->for($user)->create();

    $this->artisan('vendra-console:user-grant', ['--email' => 'ops@vendra.test'])
        ->expectsOutputToContain('Console access granted to [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->forUser($user)->sole()->active)->toBeTrue();
});

it('reports a user who already has console access without changing anything', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-grant', ['--email' => 'ops@vendra.test'])
        ->expectsOutputToContain('[ops@vendra.test] already has console access.')
        ->assertSuccessful();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeTrue()
        ->and(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('points at the password command instead of changing the password of a user who already has console access', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('vendra-console:user-grant', ['--email' => 'ops@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('[ops@vendra.test] already has console access. The password was not changed. Use vendra-console:user-password to issue a new password.')
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue()
        ->and(Console::query()->forUser($consoleUser)->count())->toBe(1);
});

it('requires an email or a username to grant console access', function (array $options): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);

    $this->artisan('vendra-console:user-grant', $options)
        ->expectsOutputToContain('Granting console access requires --email or --username.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0);
})->with([
    'neither' => [['--no-interaction' => true]],
    'blank email' => [['--email' => '   ']],
    'blank username' => [['--username' => '   ']],
]);

it('points at the create command when no tenantless user matches', function (): void {
    $this->artisan('vendra-console:user-grant', ['--username' => 'no_such_user'])
        ->expectsOutputToContain('No tenantless user has the username [no_such_user]. Use vendra-console:user-create to create one.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0);
});

it('does not grant console access when the identifiers match different users', function (): void {
    $emailUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'first_user']);
    User::factory()->create(['tenant_id' => null, 'username' => 'second_user']);

    $this->artisan('vendra-console:user-grant', ['--email' => $emailUser->email, '--username' => 'second_user'])
        ->expectsOutputToContain('The --email and --username options identify different users.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0);
});

it('names the identifier that matches no user instead of reporting a mismatch', function (string $matchingIdentifier, string $expectedMessage): void {
    $user = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name', 'email' => 'ops@vendra.test']);

    $this->artisan('vendra-console:user-grant', [
        '--email' => $matchingIdentifier === 'email' ? $user->email : 'missing@vendra.test',
        '--username' => $matchingIdentifier === 'username' ? $user->username : 'missing_user',
    ])
        ->expectsOutputToContain($expectedMessage)
        ->doesntExpectOutputToContain('identify different users')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0);
})->with([
    'unknown username' => ['email', 'No tenantless user has the username [missing_user].'],
    'unknown email' => ['username', 'No tenantless user has the email [missing@vendra.test].'],
]);

it('rejects a password that fails the password rules without granting console access', function (string $password): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);

    $this->artisan('vendra-console:user-grant', ['--email' => 'ops@vendra.test', '--password' => $password])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0)
        ->and($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
})->with(['too short' => 'short', 'empty' => '', 'whitespace' => '        ']);

it('grants console access to a tenantless user inside a tenant context', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    makeCurrentTestTenant();
    $tenantUser = User::factory()->create(['email' => $user->email]);

    $this->artisan('vendra-console:user-grant', ['--email' => 'ops@vendra.test'])
        ->expectsOutputToContain('Console access granted to [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->forUser($user)->sole()->active)->toBeTrue()
        ->and(Console::query()->forUser($tenantUser)->exists())->toBeFalse();
});

it('searches tenantless users without console access when an interactive run names no user', function (): void {
    grantConsoleAccess(User::factory()->create(['tenant_id' => null, 'email' => 'ops-console@vendra.test', 'username' => 'ops_console']));
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'ops_user']);
    makeCurrentTestTenant();
    User::factory()->create(['email' => 'ops-tenant@vendra.test', 'username' => 'ops_tenant']);
    forgetCurrentTestTenant();

    $this->artisan('vendra-console:user-grant')
        ->expectsSearch('Which user should get console access?', 'ops@vendra.test', 'ops', ['ops@vendra.test' => 'ops@vendra.test (ops_user)'])
        ->expectsOutputToContain('Console access granted to [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->active()->forUser($user)->exists())->toBeTrue();
});

it('matches the search term literally and ignores its case', function (): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'ops_user']);
    User::factory()->create(['tenant_id' => null, 'email' => 'opsx@vendra.test', 'username' => 'opsxuser']);

    $this->artisan('vendra-console:user-grant')
        ->expectsSearch('Which user should get console access?', 'ops@vendra.test', 'OPS_', ['ops@vendra.test' => 'ops@vendra.test (ops_user)'])
        ->expectsOutputToContain('Console access granted to [ops@vendra.test].')
        ->assertSuccessful();
});

it('fails instead of searching when every tenantless user already has console access', function (): void {
    grantConsoleAccess(User::factory()->create(['tenant_id' => null]));

    $this->artisan('vendra-console:user-grant')
        ->expectsOutputToContain('Every tenantless user already has console access. Use vendra-console:user-create to create one.')
        ->assertFailed();
});
