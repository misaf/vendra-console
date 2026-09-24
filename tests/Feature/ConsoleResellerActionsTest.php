<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Widgets\PlatformMetrics;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraReseller\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Filament\Pages\Auth\Login;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function actingConsoleAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => null]);

    Console::factory()->active()->for($admin)->create();

    actingAs($admin, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $admin;
}

function consoleResellerUserFor(Reseller $reseller, array $attributes = []): User
{
    $user = User::factory()->create([
        'tenant_id' => null,
        ...$attributes,
    ]);

    $reseller->user()->associate($user)->save();

    return $user;
}

it('changes a reseller plan through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->maxUnits(2))->create();
    $newPlan = Plan::factory()->active()->maxUnits(5)->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => $newPlan->getKey()]);

    expect($reseller->activeSubscription()?->plan_id)->toBe($newPlan->getKey());
});

it('blocks a plan change that cannot hold the current stores', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $currentPlan = Plan::factory()->active()->maxUnits(2)->create();
    Subscription::factory()->forSubscriber($reseller)->for($currentPlan)->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => Plan::factory()->active()->maxUnits(1)->create()->getKey()]);

    expect($reseller->activeSubscription()?->plan_id)->toBe($currentPlan->getKey());
});

it('renews the subscription through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active())->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller));

    expect($reseller->subscriptions()->count())->toBe(2)
        ->and($reseller->subscriptions()->active()->count())->toBe(1);
});

it('blocks a renewal that cannot hold the current stores', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $plan = Plan::factory()->active()->maxUnits(1)->create();
    Subscription::factory()->forSubscriber($reseller)->for($plan)->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller))
        ->assertNotified(__('vendra-console::messages.renewal_blocked'));

    expect($reseller->subscriptions()->count())->toBe(1);
});

it('deactivates and reactivates a reseller from the table through the domain action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create(['active' => true]);

    livewire(ListResellers::class)
        ->assertTableColumnExists('active', fn (IsActiveIconColumn $column): bool => true, $reseller)
        ->assertActionHidden(TestAction::make('activateReseller')->table($reseller))
        ->callAction(TestAction::make('deactivateReseller')->table($reseller))
        ->assertNotified(__('vendra-console::messages.deactivated'));

    expect($reseller->refresh()->active)->toBeFalse();

    livewire(ListResellers::class)
        ->assertActionHidden(TestAction::make('deactivateReseller')->table($reseller))
        ->callAction(TestAction::make('activateReseller')->table($reseller))
        ->assertNotified(__('vendra-console::messages.activated'));

    expect($reseller->refresh()->active)->toBeTrue();
});

it('offboards a reseller through the table row action with an audit reason', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active())->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('delete')->table($reseller), [
            'offboarding_reason' => '  Contract terminated by the platform.  ',
        ])
        ->assertHasNoActionErrors();

    $offboardedReseller = Reseller::query()->withTrashed()->findOrFail($reseller->getKey());

    expect($offboardedReseller->trashed())->toBeTrue()
        ->and($offboardedReseller->offboarding_reason)->toBe('Contract terminated by the platform.');
});

it('hides account and subscription actions on an offboarded reseller', function (string $action): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active())->create();
    resolve(OffboardResellerAction::class)->execute($reseller, 'Contract ended.');

    livewire(ListResellers::class)
        ->loadTable()
        ->filterTable('trashed', ['value' => 'trashed'])
        ->assertActionHidden(TestAction::make($action)->table($reseller));
})->with([
    'changeUserPassword',
    'changeUserEmail',
    'replaceUserAccount',
    'changePlan',
    'renew',
    'extendSubscription',
    'cancelSubscription',
    'reactivateSubscription',
]);

it('changes a reseller user password through the table row action', function (): void {
    $admin = actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $user = consoleResellerUserFor($reseller);
    $originalRememberToken = $user->getRememberToken();

    livewire(ListResellers::class)
        ->assertActionVisible(TestAction::make('changeUserPassword')->table($reseller))
        ->assertActionEnabled(TestAction::make('changeUserPassword')->table($reseller))
        ->callAction(TestAction::make('changeUserPassword')->table($reseller), [
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $user->refresh();

    expect(Hash::check('NewSecure123', $user->password))->toBeTrue()
        ->and($user->getRememberToken())->not->toBe($originalRememberToken)
        ->and(auth('console')->user()?->is($admin))->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('reseller'));

    livewire(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'NewSecure123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('reseller')->user()?->is($user))->toBeTrue();
});

it('requires confirmation when changing a reseller user password', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $user = consoleResellerUserFor($reseller);
    $originalPassword = $user->password;

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changeUserPassword')->table($reseller), [
            'password' => 'NewSecure123',
            'password_confirmation' => 'Different123',
        ])
        ->assertHasActionErrors(['password' => 'confirmed']);

    expect($user->fresh()?->password)->toBe($originalPassword);
});

it('rejects a reseller user email another active user already holds', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $user = consoleResellerUserFor($reseller);
    User::factory()->create(['tenant_id' => null, 'email' => 'taken@example.com']);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changeUserEmail')->table($reseller), ['email' => 'taken@example.com'])
        ->assertHasFormErrors(['email' => 'unique']);

    expect($user->fresh()?->email)->not->toBe('taken@example.com');
});

it('accepts a reseller user email that only a store user holds', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $user = consoleResellerUserFor($reseller);
    User::factory()->forTenant(createTestTenant())->create(['email' => 'store-user@example.com']);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changeUserEmail')->table($reseller), ['email' => 'store-user@example.com'])
        ->assertHasNoFormErrors();

    expect($user->fresh()?->email)->toBe('store-user@example.com');
});

it('validates a replacement reseller email as strictly as reseller creation', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $originalUser = consoleResellerUserFor($reseller);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('replaceUserAccount')->table($reseller), [
            'username' => 'replacement',
            'email' => 'replacement@localhost',
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])
        ->assertHasFormErrors(['email']);

    expect($reseller->refresh()->user_id)->toBe($originalUser->getKey());
});

it('updates a reseller user email through the domain action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $user = consoleResellerUserFor($reseller);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changeUserEmail')->table($reseller), ['email' => 'NEW-USER@EXAMPLE.COM'])
        ->assertHasNoActionErrors();

    expect($user->fresh()?->email)->toBe('new-user@example.com');
});

it('replaces a reseller main account while preserving the old identity', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $originalUser = consoleResellerUserFor($reseller);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('replaceUserAccount')->table($reseller), [
            'username' => 'replacement',
            'email' => 'replacement@example.com',
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])
        ->assertHasNoActionErrors();

    expect(Reseller::forUser($originalUser))->toBeNull()
        ->and($originalUser->fresh()?->trashed())->toBeFalse()
        ->and($reseller->refresh()->user->email)->toBe('replacement@example.com');
});

it('extends cancels and reactivates a reseller subscription through domain actions', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $subscription = Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active())->create();
    $extendedUntil = $subscription->ends_at?->copy()->addMonth();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('extendSubscription')->table($reseller), ['ends_at' => $extendedUntil?->toDateTimeString()])
        ->assertHasNoActionErrors();

    expect($subscription->fresh()?->ends_at?->equalTo($extendedUntil))->toBeTrue();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('cancelSubscription')->table($reseller))
        ->assertHasNoActionErrors();

    expect($subscription->fresh()?->status)->toBe(SubscriptionStatus::Cancelled);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('reactivateSubscription')->table($reseller))
        ->assertHasNoActionErrors();

    expect($reseller->subscriptions()->count())->toBe(2)
        ->and($reseller->activeSubscription())->not->toBeNull();
});

it('renders the platform metrics widget', function (): void {
    actingConsoleAdmin();
    Reseller::factory()->active()->count(2)->create();

    livewire(PlatformMetrics::class)->assertOk();
});
