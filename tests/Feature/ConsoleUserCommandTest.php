<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Database\Seeders\ConsoleUserSeeder;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

function printedConsolePassword(string $output): string
{
    $line = Str::of($output)->explode(PHP_EOL)->first(fn (string $line): bool => str_contains($line, 'Password'));

    return Str::afterLast(mb_trim((string) $line), ' ');
}

function grantConsoleAccess(User $user): void
{
    Console::factory()->for($user)->create();
}

it('seeds a console user on a fresh install and prints its generated password', function (): void {
    Config::set('app.url', 'https://www.vendra.test');

    Artisan::call('db:seed', ['--class' => ConsoleUserSeeder::class, '--force' => true]);

    $output = Artisan::output();
    $consoleUser = User::query()->sole();

    expect($consoleUser->email)->toBe('console@www.vendra.test')
        ->and($consoleUser->tenant_id)->toBeNull()
        ->and($consoleUser->username)->toBe('console')
        ->and($consoleUser->hasVerifiedEmail())->toBeTrue()
        ->and($consoleUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and($output)->toContain('https://console.www.vendra.test')
        ->and(printedConsolePassword($output))->toHaveLength(32)
        ->and(Hash::check(printedConsolePassword($output), $consoleUser->password))->toBeTrue();
});

it('does not seed a console user when a console grant already exists', function (): void {
    $existingConsoleUser = User::factory()->create(['tenant_id' => null]);
    grantConsoleAccess($existingConsoleUser);

    Artisan::call('db:seed', ['--class' => ConsoleUserSeeder::class, '--force' => true]);

    expect(Artisan::output())->not->toContain('Password')
        ->and(User::query()->sole()->is($existingConsoleUser))->toBeTrue();
});

it('fails the seed when the default console email belongs to an existing user', function (): void {
    Config::set('app.url', 'https://vendra.test');
    User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test']);

    expect(fn (): int => Artisan::call('db:seed', ['--class' => ConsoleUserSeeder::class, '--force' => true, '--no-interaction' => true]))
        ->toThrow(RuntimeException::class, 'No console user was seeded.')
        ->and(Console::query()->count())->toBe(0);
});

it('creates a console user with a generated password and prints it', function (): void {
    Config::set('app.url', 'https://vendra.test');

    expect(Artisan::call('console:user'))->toBe(0);

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

    $this->artisan('console:user', ['--email' => 'OPS@vendra.test', '--password' => 'the-new-password'])
        ->expectsOutputToContain('the-new-password')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(1)
        ->and(Hash::check('the-new-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('trims and lowercases the given email before creating a console user', function (): void {
    $this->artisan('console:user', ['--email' => ' OPS@Vendra.test ', '--password' => 'the-new-password'])
        ->expectsOutputToContain('ops@vendra.test')
        ->assertSuccessful();

    expect(User::query()->sole()->email)->toBe('ops@vendra.test');
});

it('rejects an invalid email without creating a console user', function (): void {
    $this->artisan('console:user', ['--email' => 'not-an-email'])
        ->expectsOutputToContain('valid email')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('rejects a password that fails the password rules without creating a console user', function (): void {
    $this->artisan('console:user', ['--email' => 'ops@vendra.test', '--password' => 'short'])
        ->expectsOutputToContain('at least 8 characters')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('keeps an existing console user password when the given password fails the password rules', function (): void {
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);
    grantConsoleAccess($consoleUser);

    $this->artisan('console:user', ['--email' => 'ops@vendra.test', '--password' => 'short'])
        ->assertFailed();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeTrue();
});

it('falls back to localhost for the email and console url when the app url has no host', function (): void {
    Config::set('app.url', '');

    expect(Artisan::call('console:user'))->toBe(0)
        ->and(Artisan::output())->toContain('console@localhost')
        ->toContain('https://console.localhost');
});

it('suffixes the username when another platform user already holds it', function (): void {
    User::factory()->create(['tenant_id' => null, 'username' => 'operations_1', 'email' => 'operations_1@a.test']);

    $this->artisan('console:user', ['--email' => 'operations_1@b.test'])->assertSuccessful();

    expect(User::query()->where('email', 'operations_1@b.test')->sole()->username)->toBe('operations_2');
});

it('does not grant console access to an existing user when the prompt is declined', function (): void {
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'reseller@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);

    $this->artisan('console:user', ['--email' => 'reseller@vendra.test'])
        ->expectsConfirmation('[reseller@vendra.test] is an existing user without console access. Grant console access and issue a new password?', 'no')
        ->assertFailed();

    expect(Console::query()->count())->toBe(0)
        ->and(Hash::check('the-old-password', $user->refresh()->password))->toBeTrue();
});

it('grants console access to an existing user once the prompt is confirmed', function (): void {
    $user = User::factory()->create(['tenant_id' => null, 'email' => 'reseller@vendra.test']);

    $this->artisan('console:user', ['--email' => 'reseller@vendra.test', '--password' => 'the-new-password'])
        ->expectsConfirmation('[reseller@vendra.test] is an existing user without console access. Grant console access and issue a new password?', 'yes')
        ->expectsOutputToContain('Console access granted and password updated.')
        ->assertSuccessful();

    expect($user->refresh()->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check('the-new-password', $user->password))->toBeTrue();
});

it('revokes console access from the user given by email', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($revokedUser);
    Console::factory()->create();

    $this->artisan('console:user', ['--email' => 'OPS@vendra.test', '--revoke' => true])
        ->expectsOutputToContain('Console access revoked from [ops@vendra.test].')
        ->assertSuccessful();

    expect($revokedUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('refuses to revoke the last console user from the command', function (): void {
    $lastUser = User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);
    grantConsoleAccess($lastUser);

    $this->artisan('console:user', ['--email' => 'ops@vendra.test', '--revoke' => true])
        ->expectsOutputToContain('[ops@vendra.test] is the last console user.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(1);
});

it('requires an email to revoke console access', function (): void {
    Console::factory()->count(2)->create();

    $this->artisan('console:user', ['--revoke' => true])
        ->expectsOutputToContain('The --revoke option requires --email.')
        ->assertFailed();

    expect(Console::query()->count())->toBe(2);
});

it('asks before granting console access when the default email belongs to an existing user', function (): void {
    Config::set('app.url', 'https://vendra.test');
    $user = User::factory()->create([
        'tenant_id' => null,
        'email' => 'console@vendra.test',
        'password' => Hash::make('the-old-password'),
    ]);

    $this->artisan('console:user')
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

    $this->artisan('console:user')
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

    $this->artisan('console:user')
        ->expectsConfirmation('[console@vendra.test] is already a console user. Issue a new password?', 'yes')
        ->expectsOutputToContain('Console user password updated.')
        ->assertSuccessful();

    expect(Hash::check('the-old-password', $consoleUser->refresh()->password))->toBeFalse();
});
