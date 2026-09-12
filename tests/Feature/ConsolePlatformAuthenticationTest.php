<?php

declare(strict_types=1);

use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Uri;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function consolePlatformUser(array $attributes = []): User
{
    $consoleUser = User::factory()->create([
        'tenant_id' => null,
        'password' => Hash::make('platform-password'),
        ...$attributes,
    ]);

    DB::table('console_users')->insert([
        'user_id' => $consoleUser->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $consoleUser;
}

it('authenticates the platform user when a tenant row shares the email', function (): void {
    $tenant = createTestTenant();
    $email = 'console-shared@example.test';

    User::factory()->forTenant($tenant)->create([
        'username' => 'console_tenant',
        'email' => $email,
        'password' => Hash::make('tenant-password'),
    ]);

    $consoleUser = consolePlatformUser([
        'username' => 'console_user',
        'email' => $email,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('console'));

    livewire(Login::class)
        ->fillForm([
            'email' => $email,
            'password' => 'platform-password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('console')->id())->toBe($consoleUser->getKey())
        ->and(auth('web')->check())->toBeFalse()
        ->and(auth('reseller')->check())->toBeFalse();
});

it('rejects the tenant password and wrong passwords on the console', function (): void {
    $tenant = createTestTenant();
    $email = 'console-wrong@example.test';

    User::factory()->forTenant($tenant)->create([
        'username' => 'console_tenant2',
        'email' => $email,
        'password' => Hash::make('tenant-password'),
    ]);

    consolePlatformUser([
        'username' => 'console_user2',
        'email' => $email,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('console'));

    livewire(Login::class)
        ->fillForm([
            'email' => $email,
            'password' => 'tenant-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth('console')->check())->toBeFalse();

    livewire(Login::class)
        ->fillForm([
            'email' => $email,
            'password' => 'definitely-wrong',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth('console')->check())->toBeFalse();
});

it('does not let a tenant-only user into the console', function (): void {
    $tenant = createTestTenant();
    $email = 'tenant-only@example.test';

    $tenantUser = User::factory()->forTenant($tenant)->create([
        'username' => 'tenant_only',
        'email' => $email,
        'password' => Hash::make('tenant-password'),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('console'));

    livewire(Login::class)
        ->fillForm([
            'email' => $email,
            'password' => 'tenant-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth('console')->check())->toBeFalse();

    actingAs($tenantUser, 'web');

    expect(auth('web')->id())->toBe($tenantUser->getKey())
        ->and(auth('console')->check())->toBeFalse()
        ->and($tenantUser->canAccessPanel(Filament::getPanel('console')))->toBeFalse();
});

it('restores console sessions only for the platform user via remember token', function (): void {
    $tenant = createTestTenant();
    $email = 'console-remember@example.test';

    $tenantUser = User::factory()->forTenant($tenant)->create([
        'username' => 'console_rem_tenant',
        'email' => $email,
        'remember_token' => 'tenant-remember-token',
    ]);

    $consoleUser = consolePlatformUser([
        'username' => 'console_rem_op',
        'email' => $email,
        'remember_token' => 'platform-remember-token',
    ]);

    $provider = auth('console')->getProvider();

    expect($provider->retrieveByToken($consoleUser->getKey(), 'platform-remember-token')?->getKey())->toBe($consoleUser->getKey())
        ->and($provider->retrieveByToken($tenantUser->getKey(), 'tenant-remember-token'))->toBeNull();
});

it('removes console access when the console user grant is revoked', function (): void {
    $consoleUser = consolePlatformUser();
    $panel = Filament::getPanel('console');

    expect($consoleUser->canAccessPanel($panel))->toBeTrue();

    DB::table('console_users')->where('user_id', $consoleUser->getKey())->delete();

    expect($consoleUser->canAccessPanel($panel))->toBeFalse()
        ->and(User::query()->find($consoleUser->getKey()))->not->toBeNull();
});

it('routes the console password reset flow to the platform identity and store', function (): void {
    Notification::fake();

    $tenant = createTestTenant();
    $email = 'console-reset@example.test';

    $tenantUser = User::factory()->forTenant($tenant)->create([
        'username' => 'console_reset_tenant',
        'email' => $email,
        'password' => Hash::make('tenant-password'),
    ]);

    $consoleUser = consolePlatformUser([
        'username' => 'console_reset_user',
        'email' => $email,
    ]);

    $panel = Filament::getPanel('console');
    Filament::setCurrentPanel($panel);

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $email])
        ->call('request')
        ->assertHasNoFormErrors();

    /*
    | The host binds its own subclass of Filament's reset notification, and
    | NotificationFake matches on the exact class, so resolve whatever the
    | container hands the page rather than naming the host class here. The
    | plain token only survives on the signed reset URL.
    */
    $notificationClass = app(ResetPasswordNotification::class)::class;

    $token = null;

    Notification::assertSentTo($consoleUser, $notificationClass, function (ResetPasswordNotification $notification) use (&$token): bool {
        $token = Uri::of($notification->url)->query()->get('token');

        return true;
    });

    Notification::assertNotSentTo($tenantUser, $notificationClass);

    expect($panel->getAuthPasswordBroker())->toBe('console')
        ->and(DB::table('console_password_reset_tokens')->where('email', $email)->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', $email)->count())->toBe(0);

    livewire(ResetPassword::class, ['email' => $email, 'token' => $token])
        ->fillForm([
            'password' => 'console-rotated-password',
            'passwordConfirmation' => 'console-rotated-password',
        ])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(Hash::check('console-rotated-password', $consoleUser->fresh()->password))->toBeTrue()
        ->and(Hash::check('tenant-password', $tenantUser->fresh()->password))->toBeTrue()
        ->and(DB::table('console_password_reset_tokens')->where('email', $email)->count())->toBe(0);
});
