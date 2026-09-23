<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Console\Commands\RevokeConsoleUserCommand;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('constructs the command without resolving its actions', function (): void {
    $this->app->bind(RevokeConsoleUserAction::class, fn (): never => throw new LogicException('An action was resolved before it was needed.'));

    expect(resolve(RevokeConsoleUserCommand::class))->toBeInstanceOf(RevokeConsoleUserCommand::class);
});

it('revokes console access from the user given by email', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($revokedUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', ['--email' => 'OPS@vendra.test'])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('revokes console access from the user given by username', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    grantConsoleAccess($revokedUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', ['--username' => ' chosen_name '])
        ->expectsOutputToContain("Console access revoked from [{$revokedUser->email}].")
        ->assertSuccessful();

    expect($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

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

    $this->artisan('vendra-console:user-revoke', ['--email' => $consoleUser->email])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeFalse()
        ->and(Console::query()->active()->count())->toBe(1);
})->with(['current tenant' => false, 'authenticated tenant user' => true]);

it('does not find a soft-deleted tenantless user when revoking console access', function (): void {
    $consoleUser = User::factory()->trashed()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    makeCurrentTestTenant();

    $this->artisan('vendra-console:user-revoke', ['--email' => $consoleUser->email])
        ->expectsOutputToContain('No tenantless user has the email [ops@vendra.test].')
        ->assertFailed();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeTrue();
});

it('refuses to revoke the last console user from the command', function (): void {
    $lastUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($lastUser);

    $this->artisan('vendra-console:user-revoke', ['--email' => 'ops@vendra.test'])
        ->expectsOutputToContain('[ops@vendra.test] is the last console user. Grant console access to another user before revoking it.')
        ->assertFailed();

    expect($lastUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue();
});

it('requires an email or a username to revoke console access without interaction', function (): void {
    Console::factory()->active()->count(2)->create();

    $this->artisan('vendra-console:user-revoke', ['--no-interaction' => true])
        ->expectsOutputToContain('Revoking console access requires --email or --username.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(2);
});

it('requires a non-blank identifier to revoke console access', function (string $option): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', [$option => '   '])
        ->expectsOutputToContain('Revoking console access requires --email or --username.')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
})->with(['email' => '--email', 'username' => '--username']);

it('rejects an invalid username without revoking console access', function (string $username, string $message): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'username' => $username]);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', ['--username' => $username])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeTrue();
})->with([
    'too short' => ['ab', 'at least 3 characters'],
    'too long' => ['username_long', 'greater than 12 characters'],
    'punctuation' => ['user.name', 'letters, numbers, dashes, and underscores'],
]);

it('rejects an invalid email without revoking console access', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'not-an-email']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', ['--email' => 'not-an-email'])
        ->expectsOutputToContain('valid email')
        ->assertFailed();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeTrue();
});

it('rejects revocation when either supplied identifier is invalid', function (string $email, string $username, string $message): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    Console::factory()->active()->count(2)->create();

    $this->artisan('vendra-console:user-revoke', ['--email' => $email, '--username' => $username])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
})->with([
    'invalid email' => ['not-an-email', 'chosen_name', 'valid email'],
    'invalid username' => ['ops@vendra.test', 'user.name', 'letters, numbers, dashes, and underscores'],
    'blank email' => ['   ', 'chosen_name', 'requires --email or --username'],
    'blank username' => ['ops@vendra.test', '   ', 'requires --email or --username'],
]);

it('reports an unknown username when revoking console access', function (): void {
    Console::factory()->active()->count(2)->create();

    $this->artisan('vendra-console:user-revoke', ['--username' => 'no_such_user'])
        ->expectsOutputToContain('No tenantless user has the username [no_such_user].')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
});

it('revokes when both identifiers match the same user', function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'chosen_name']);
    grantConsoleAccess($consoleUser);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', ['--email' => ' OPS@VENDRA.TEST ', '--username' => ' chosen_name '])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect(Console::query()->forUser($consoleUser)->sole()->active)->toBeFalse();
});

it('does not revoke either user when the identifiers match different users', function (): void {
    $emailUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'first_user']);
    $usernameUser = User::factory()->create(['tenant_id' => null, 'username' => 'second_user']);
    grantConsoleAccess($emailUser);
    grantConsoleAccess($usernameUser);

    $this->artisan('vendra-console:user-revoke', ['--email' => $emailUser->email, '--username' => $usernameUser->username])
        ->expectsOutputToContain('The --email and --username options identify different users.')
        ->assertFailed();

    expect(Console::query()->active()->count())->toBe(2);
});

it('names the identifier that matches no user when the other matches', function (string $matchingIdentifier, string $expectedMessage): void {
    $user = User::factory()->create(['tenant_id' => null, 'username' => 'chosen_name', 'email' => 'ops@vendra.test']);
    grantConsoleAccess($user);
    Console::factory()->active()->create();

    $this->artisan('vendra-console:user-revoke', [
        '--email' => $matchingIdentifier === 'email' ? $user->email : 'missing@vendra.test',
        '--username' => $matchingIdentifier === 'username' ? $user->username : 'missing_user',
    ])
        ->expectsOutputToContain($expectedMessage)
        ->doesntExpectOutputToContain('identify different users')
        ->assertFailed();

    expect(Console::query()->active()->forUser($user)->exists())->toBeTrue();
})->with([
    'unknown username' => ['email', 'No tenantless user has the username [missing_user].'],
    'unknown email' => ['username', 'No tenantless user has the email [missing@vendra.test].'],
]);

it('searches console users when an interactive run names no user', function (): void {
    grantConsoleAccess(User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test', 'username' => 'ops_user']));
    grantConsoleAccess(User::factory()->create(['tenant_id' => null, 'email' => 'admin@vendra.test', 'username' => 'admin_user']));
    User::factory()->create(['tenant_id' => null, 'email' => 'ops-plain@vendra.test', 'username' => 'ops_plain']);

    $this->artisan('vendra-console:user-revoke')
        ->expectsSearch('Which console user should lose access?', 'ops@vendra.test', 'ops', ['ops@vendra.test' => 'ops@vendra.test (ops_user)'])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();
});

it('fails instead of searching when no tenantless user has console access', function (): void {
    User::factory()->create(['tenant_id' => null]);

    $this->artisan('vendra-console:user-revoke')
        ->expectsOutputToContain('No tenantless user has console access.')
        ->assertFailed();
});
