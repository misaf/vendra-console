<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Database\Seeders\ConsoleSeeder;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\PasswordGenerator;

it('seeds a console user on a fresh install and prints its generated password', function (): void {
    Config::set('vendra-console.default_email', 'ops@acme.test');

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true]);

    $output = Artisan::output();
    $printedPassword = Str::of($output)->match('/\|\s*https:\/\/[^|]+\|\s*[^|]+\|\s*(\S+)\s*\|/')->toString();
    $consoleUser = User::query()->sole();

    expect($consoleUser->email)->toBe('ops@acme.test')
        ->and($consoleUser->tenant_id)->toBeNull()
        ->and($consoleUser->username)->toBe('console')
        ->and($consoleUser->hasVerifiedEmail())->toBeTrue()
        ->and($consoleUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and($output)->toContain('https://console.vendra.test/login')
        ->and($printedPassword)->toHaveLength(PasswordGenerator::LENGTH)
        ->and(Hash::check($printedPassword, $consoleUser->password))->toBeTrue();
});

it('grants the seeded console user active console access at the configured address', function (): void {
    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true]);

    expect(User::query()->sole()->email)->toBe('console@vendra.test')
        ->and(Console::query()->active()->count())->toBe(1);
});

it('does not seed a console user when a console grant already exists', function (): void {
    $existingConsoleUser = User::factory()->create(['tenant_id' => null]);
    Console::factory()->active()->for($existingConsoleUser)->create();

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true]);

    expect(Artisan::output())->not->toContain('Password')
        ->and(User::query()->sole()->is($existingConsoleUser))->toBeTrue();
});

it('skips the seed with an error when the default console email belongs to an existing user', function (): void {
    Config::set('app.url', 'https://vendra.test');
    User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test']);

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true, '--no-interaction' => true]);

    expect(Artisan::output())->toContain('The email [console@vendra.test] or the username [console] already belongs to a tenantless user. Use vendra-console:user-grant to give that user console access, or vendra-console:user-create with a different email and username.')
        ->and(Console::query()->count())->toBe(0);
});

it('skips the seed with an error when the console username is already taken', function (): void {
    User::factory()->create(['tenant_id' => null, 'username' => 'console']);

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true, '--no-interaction' => true]);

    expect(Artisan::output())->toContain('or the username [console] already belongs to a tenantless user.')
        ->and(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(0);
});

it('skips the seed with an error when the console user was revoked', function (): void {
    $revokedUser = User::factory()->create(['tenant_id' => null, 'username' => 'console']);
    Console::factory()->inactive()->for($revokedUser)->create();

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true, '--no-interaction' => true]);

    expect(Artisan::output())->toContain('Use vendra-console:user-grant to give that user console access')
        ->and(Console::query()->active()->exists())->toBeFalse();
});
