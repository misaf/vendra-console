<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Console\Commands\CreateConsoleUserCommand;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

it('constructs the command without resolving its actions', function (): void {
    $this->app->bind(CreateConsoleUserAction::class, fn (): never => throw new LogicException('An action was resolved before it was needed.'));

    expect(resolve(CreateConsoleUserCommand::class))->toBeInstanceOf(CreateConsoleUserCommand::class);
});

it('creates a console user with a generated password and prints it', function (): void {
    Config::set('app.url', 'https://vendra.test');

    expect(Artisan::call('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test']))->toBe(0);

    $output = Artisan::output();
    $consoleUser = User::query()->sole();

    expect($output)->toContain('ops@vendra.test')
        ->toContain('https://console.vendra.test/login')
        ->and($consoleUser->canAccessPanel(Filament::getPanel('console')))->toBeTrue()
        ->and(Hash::check(printedConsolePassword($output), $consoleUser->password))->toBeTrue();
});

it('requires an email without interaction instead of falling back to the configured default address', function (): void {
    Config::set('vendra-console.default_email', 'ops@elsewhere.test');

    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--password' => 'the-new-password', '--no-interaction' => true])
        ->expectsOutputToContain('An email is required to create a console user. Use --email.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('consoles', 0);
});

it('trims and lowercases the given email before creating a console user', function (): void {
    $this->artisan('vendra-console:user-create', ['--username' => ' chosen_name ', '--email' => ' OPS@Vendra.test ', '--password' => 'the-new-password'])
        ->expectsOutputToContain('ops@vendra.test')
        ->assertSuccessful();

    expect(User::query()->sole()->email)->toBe('ops@vendra.test')
        ->and(User::query()->sole()->username)->toBe('chosen_name');
});

it('rejects an invalid email without creating a console user', function (): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'not-an-email'])
        ->expectsOutputToContain('valid email')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('rejects a password that fails the password rules without creating a console user', function (string $password): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test', '--password' => $password])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
})->with(['too short' => 'short', 'empty' => '', 'whitespace' => '        ']);

it('generates a password that satisfies the application password rules', function (): void {
    $passwordDefaults = Password::$defaultCallback;
    Password::defaults(fn () => Password::min(40));

    try {
        expect(Artisan::call('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test']))->toBe(0);

        $password = printedConsolePassword(Artisan::output());

        expect($password)->toHaveLength(40)
            ->and(Hash::check($password, User::query()->sole()->password))->toBeTrue();
    } finally {
        Password::$defaultCallback = $passwordDefaults;
    }
});

it('rejects an email the shared user rules reject', function (): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'console@localhost'])
        ->expectsOutputToContain('valid email')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('rejects an email or username a tenantless user already holds and points at the siblings', function (array $options, string $error, bool $hasConsoleAccess): void {
    $existingUser = User::factory()->create(['tenant_id' => null, 'username' => 'existing_one', 'email' => 'ops@vendra.test']);

    if ($hasConsoleAccess) {
        grantConsoleAccess($existingUser);
    }

    $this->artisan('vendra-console:user-create', $options)
        ->expectsOutputToContain($error.' Use vendra-console:user-grant or vendra-console:user-password.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(Console::query()->count())->toBe($hasConsoleAccess ? 1 : 0);
})->with([
    'email' => [['--username' => 'chosen_name', '--email' => 'ops@vendra.test'], 'The email [ops@vendra.test] already belongs to a tenantless user.', false],
    'email of a console user' => [['--username' => 'chosen_name', '--email' => 'ops@vendra.test'], 'The email [ops@vendra.test] already belongs to a tenantless user.', true],
    'username' => [['--username' => 'existing_one', '--email' => 'other@vendra.test'], 'The username [existing_one] already belongs to a tenantless user.', false],
]);

it('asks again when the answer names an email a tenantless user already holds', function (): void {
    User::factory()->create(['tenant_id' => null, 'email' => 'ops@vendra.test']);

    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name'])
        ->expectsQuestion('Email', 'OPS@vendra.test')
        ->expectsOutputToContain('The email [ops@vendra.test] already belongs to a tenantless user.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('requires a username without interaction', function (): void {
    $this->artisan('vendra-console:user-create', ['--email' => 'ops@vendra.test', '--no-interaction' => true])
        ->expectsOutputToContain('A username is required')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
});

it('asks for the email and username when they are omitted', function (): void {
    $this->artisan('vendra-console:user-create', ['--password' => 'the-new-password'])
        ->expectsQuestion('Email', ' OPS@Vendra.test ')
        ->expectsQuestion('Username', 'chosen_name')
        ->assertSuccessful();

    expect(User::query()->sole())
        ->email->toBe('ops@vendra.test')
        ->username->toBe('chosen_name');
});

it('rejects an answer to an identifier question that fails the user rules', function (string $question, string $answer, string $error): void {
    $options = array_diff_key(['--email' => 'ops@vendra.test', '--username' => 'chosen_name'], [$question === 'Email' ? '--email' : '--username' => true]);

    $this->artisan('vendra-console:user-create', $options)
        ->expectsQuestion($question, $answer)
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'empty email' => ['Email', '', 'An email is required.'],
    'invalid email' => ['Email', 'not-an-email', 'valid email'],
    'empty username' => ['Username', '', 'A username is required.'],
    'invalid username' => ['Username', 'user.name', 'username'],
]);

it('rejects an invalid username without creating a user', function (string $username): void {
    $this->artisan('vendra-console:user-create', ['--username' => $username, '--email' => 'ops@vendra.test'])
        ->expectsOutputToContain('username')
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
    $this->artisan('vendra-console:user-create', ['--username' => $username, '--email' => 'ops@vendra.test'])
        ->assertSuccessful();

    expect(User::query()->sole()->username)->toBe($username);
})->with(['minimum' => 'a_1', 'maximum' => 'user-name_12']);

it('allows a username held by a tenant user or a soft-deleted tenantless user', function (): void {
    User::factory()->trashed()->create(['tenant_id' => null, 'username' => 'chosen_name']);
    makeCurrentTestTenant();
    User::factory()->create(['username' => 'chosen_name']);
    forgetCurrentTestTenant();

    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test'])
        ->assertSuccessful();

    expect(User::query()->whereNull('tenant_id')->sole()->username)->toBe('chosen_name');
});

it('rejects a blank email', function (string $email): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => $email])
        ->expectsOutputToContain('An email is required to create a console user. Use --email.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Console::query()->count())->toBe(0);
})->with(['empty' => '', 'whitespace' => '   ']);

it('asks for the password without echo when --password is given no value', function (): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test', '--password' => null])
        ->expectsQuestion('Password', 'the-new-password')
        ->assertSuccessful();

    expect(Hash::check('the-new-password', User::query()->sole()->password))->toBeTrue();
});

it('rejects an answer to the password question that fails the password rules', function (string $answer, string $error): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test', '--password' => null])
        ->expectsQuestion('Password', $answer)
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with(['empty' => ['', 'A password is required.'], 'too short' => ['short', 'password']]);

it('rejects a valueless --password without interaction instead of generating one', function (): void {
    $this->artisan('vendra-console:user-create', ['--username' => 'chosen_name', '--email' => 'ops@vendra.test', '--password' => null, '--no-interaction' => true])
        ->expectsOutputToContain('password')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
