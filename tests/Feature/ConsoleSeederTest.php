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

it('seeds a console user on a fresh install and prints its generated password', function (): void {
    Config::set('app.url', 'https://www.vendra.test');

    Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true]);

    $output = Artisan::output();
    $printedPassword = Str::of($output)->match('/\|\s*https:\/\/[^|]+\|\s*[^|]+\|\s*(\S+)\s*\|/')->toString();
    $consoleUser = User::query()->sole();

    expect($consoleUser->email)->toBe('console@www.vendra.test')
        ->and($consoleUser->tenant_id)->toBeNull()
        ->and($consoleUser->username)->toBe('console')
        ->and($consoleUser->hasVerifiedEmail())->toBeTrue()
        ->and($consoleUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and($output)->toContain('https://console.www.vendra.test')
        ->and($printedPassword)->toHaveLength(16)
        ->and(Hash::check($printedPassword, $consoleUser->password))->toBeTrue();
});

it('grants the seeded console user active console access', function (): void {
    Config::set('app.url', 'https://vendra.test');

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

it('fails the seed when the default console email belongs to an existing user', function (): void {
    Config::set('app.url', 'https://vendra.test');
    User::factory()->create(['tenant_id' => null, 'email' => 'console@vendra.test']);

    expect(fn (): int => Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true, '--no-interaction' => true]))
        ->toThrow(RuntimeException::class, 'Console username or email')
        ->and(Console::query()->count())->toBe(0);
});

it('fails the seed when the console username is already taken', function (): void {
    User::factory()->create(['tenant_id' => null, 'username' => 'console']);

    expect(fn (): int => Artisan::call('db:seed', ['--class' => ConsoleSeeder::class, '--force' => true, '--no-interaction' => true]))
        ->toThrow(RuntimeException::class, 'Console username or email')
        ->and(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe(0);
});
