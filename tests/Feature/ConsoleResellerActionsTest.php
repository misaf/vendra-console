<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Widgets\ConsoleOverview;
use Misaf\VendraReseller\Filament\Pages\Auth\Login;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function actingConsoleAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => null]);

    DB::table('console_users')->insert([
        'user_id' => $admin->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

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

    $reseller->users()->attach($user->getKey());

    return $user;
}

function userHasActiveMembership(Reseller $reseller, User $user): bool
{
    return DB::table('reseller_users')
        ->where('reseller_id', $reseller->getKey())
        ->where('user_id', $user->getKey())
        ->whereNull('deleted_at')
        ->exists();
}

it('changes a reseller plan through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->maxUnits(2))->create();
    $newPlan = Plan::factory()->maxUnits(5)->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => $newPlan->getKey()]);

    expect($reseller->activeSubscription()?->plan_id)->toBe($newPlan->getKey());
});

it('blocks a plan change that cannot hold the current stores', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    $currentPlan = Plan::factory()->maxUnits(2)->create();
    Subscription::factory()->forSubscriber($reseller)->for($currentPlan)->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => Plan::factory()->maxUnits(1)->create()->getKey()]);

    expect($reseller->activeSubscription()?->plan_id)->toBe($currentPlan->getKey());
});

it('renews the subscription through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory())->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller));

    expect($reseller->subscriptions()->count())->toBe(2)
        ->and($reseller->subscriptions()->active()->count())->toBe(1);
});

it('offboards a reseller through the table row action with an audit reason', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory())->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('delete')->table($reseller), [
            'offboarding_reason' => 'Contract terminated by the platform.',
        ])
        ->assertHasNoActionErrors();

    $offboardedReseller = Reseller::query()->withTrashed()->findOrFail($reseller->getKey());

    expect($offboardedReseller->trashed())->toBeTrue()
        ->and($offboardedReseller->offboarding_reason)->toBe('Contract terminated by the platform.');
});

it('changes a reseller user password through the table row action', function (): void {
    $admin = actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
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

    $reseller = Reseller::factory()->create();
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

it('shows why a reseller without a user cannot change its password yet', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();

    livewire(ListResellers::class)
        ->assertActionVisible(TestAction::make('createUserAccount')->table($reseller))
        ->assertActionVisible(TestAction::make('changeUserPassword')->table($reseller))
        ->assertActionDisabled(TestAction::make('changeUserPassword')->table($reseller));
});

it('creates a user login for an existing reseller', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('createUserAccount')->table($reseller), [
            'username' => 'user_login',
            'email' => 'user@existing.test',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $user = $reseller->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('user@existing.test')
        ->and($user->tenant_id)->toBeNull()
        ->and(Hash::check('Secure123', $user->password))->toBeTrue();
});

it('updates disables and re-enables a reseller user through domain actions', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    $user = consoleResellerUserFor($reseller);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changeUserEmail')->table($reseller), ['email' => 'NEW-USER@EXAMPLE.COM'])
        ->assertHasNoActionErrors();

    expect($user->fresh()?->email)->toBe('new-user@example.com')
        ->and($reseller->fresh()?->email)->toBe('new-user@example.com');

    livewire(ListResellers::class)
        ->callAction(TestAction::make('disableUserAccount')->table($reseller))
        ->assertHasNoActionErrors();

    expect($reseller->user())->toBeNull()
        ->and(userHasActiveMembership($reseller, $user))->toBeFalse()
        ->and($user->fresh()?->trashed())->toBeFalse();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('enableUserAccount')->table($reseller))
        ->assertHasNoActionErrors();

    expect($reseller->user()?->is($user))->toBeTrue();
});

it('replaces a reseller user while preserving the old account as history', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    $originalUser = consoleResellerUserFor($reseller);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('replaceUserAccount')->table($reseller), [
            'username' => 'replacement',
            'email' => 'replacement@example.com',
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])
        ->assertHasNoActionErrors();

    expect(userHasActiveMembership($reseller, $originalUser))->toBeFalse()
        ->and($originalUser->fresh()?->trashed())->toBeFalse()
        ->and($reseller->user()?->email)->toBe('replacement@example.com');
});

it('extends cancels and reactivates a reseller subscription through domain actions', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->create();
    $subscription = Subscription::factory()->forSubscriber($reseller)->for(Plan::factory())->create();
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

it('renders the console overview widget', function (): void {
    actingConsoleAdmin();
    Reseller::factory()->count(2)->create();

    livewire(ConsoleOverview::class)->assertOk();
});
