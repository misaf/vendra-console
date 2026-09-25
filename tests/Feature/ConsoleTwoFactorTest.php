<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function twoFactorConsoleUser(bool $withAppAuthentication = true): User
{
    $factory = User::factory();

    $user = ($withAppAuthentication ? $factory->withAppAuthentication() : $factory)->create([
        'tenant_id' => null,
        'email' => 'ops@vendra.test',
        'password' => Hash::make('console-password'),
    ]);

    Console::factory()->active()->for($user)->create();

    return $user;
}

it('sends a console user without an authenticator app to set one up', function (): void {
    actingAs(twoFactorConsoleUser(withAppAuthentication: false), 'console');

    $this->get('https://console.vendra.test')
        ->assertRedirect(Filament::getPanel('console')->getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('lets a console user with an authenticator app into the panel', function (): void {
    actingAs(twoFactorConsoleUser(), 'console');

    $this->get('https://console.vendra.test')->assertOk();
});

it('signs a console user in only after the authenticator code', function (): void {
    $user = twoFactorConsoleUser();
    Filament::setCurrentPanel(Filament::getPanel('console'));

    $login = livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'console-password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('console')->check())->toBeFalse();

    $login
        ->fillForm(['app' => ['code' => '000000']], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasFormErrors(['app.code'], 'multiFactorChallengeForm');

    expect(auth('console')->check())->toBeFalse();

    $login
        ->fillForm(['app' => ['code' => AppAuthentication::make()->getCurrentCode($user)]], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasNoFormErrors(form: 'multiFactorChallengeForm');

    expect(auth('console')->id())->toBe($user->getKey());
});

it('resets a reseller user two-factor through the table row action', function (): void {
    actingAs(twoFactorConsoleUser(), 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    $user = User::factory()->create(['tenant_id' => null]);
    $reseller = Reseller::factory()->active()->for($user)->create();
    $plainReseller = Reseller::factory()->active()->create();

    livewire(ListResellers::class)
        ->assertActionHidden(TestAction::make('resetUserTwoFactor')->table($reseller))
        ->assertActionHidden(TestAction::make('resetUserTwoFactor')->table($plainReseller));

    $app = AppAuthentication::make();
    $app->saveSecret($user, $app->generateSecret());
    $app->saveRecoveryCodes($user, $app->generateRecoveryCodes());

    livewire(ListResellers::class)
        ->callAction(TestAction::make('resetUserTwoFactor')->table($reseller->refresh()))
        ->assertNotified();

    $user->refresh();

    expect($user->hasAppAuthentication())->toBeFalse()
        ->and($user->app_authentication_recovery_codes)->toBeNull();
});

describe('vendra-console:user-two-factor-reset', function (): void {
    it('resets the console user given by email', function (): void {
        $user = twoFactorConsoleUser();

        $this->artisan('vendra-console:user-two-factor-reset', ['--email' => 'OPS@vendra.test', '--force' => true])
            ->expectsOutputToContain('Two-factor authentication reset for [ops@vendra.test].')
            ->assertSuccessful();

        expect($user->refresh()->hasAppAuthentication())->toBeFalse();
    });

    it('asks before resetting and keeps the app when declined', function (): void {
        $user = twoFactorConsoleUser();

        $this->artisan('vendra-console:user-two-factor-reset', ['--email' => 'ops@vendra.test'])
            ->expectsConfirmation('Remove the authenticator app and recovery codes of [ops@vendra.test]?', 'no')
            ->assertFailed();

        expect($user->refresh()->hasAppAuthentication())->toBeTrue();
    });

    it('reports a console user without two-factor', function (): void {
        twoFactorConsoleUser(withAppAuthentication: false);

        $this->artisan('vendra-console:user-two-factor-reset', ['--email' => 'ops@vendra.test', '--force' => true])
            ->expectsOutputToContain('[ops@vendra.test] has no two-factor authentication set up.')
            ->assertSuccessful();
    });

    it('refuses a user without console access', function (): void {
        $user = User::factory()->withAppAuthentication()->create(['tenant_id' => null, 'email' => 'reseller@vendra.test']);

        $this->artisan('vendra-console:user-two-factor-reset', ['--email' => 'reseller@vendra.test', '--force' => true])
            ->expectsOutputToContain('has no console access. Use vendra-console:user-grant to grant it.')
            ->assertFailed();

        expect($user->refresh()->hasAppAuthentication())->toBeTrue();
    });
});
