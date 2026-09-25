<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\EditPlan;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ViewReseller;
use Misaf\VendraConsole\Filament\Widgets\PlatformMetrics;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraReseller\Actions\CreditResellerWalletAction;
use Misaf\VendraReseller\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Filament\Pages\Auth\Login;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;
use Misaf\VendraTransaction\Database\Factories\TransactionGatewayFactory;
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
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => Plan::factory()->active()->maxUnits(1)->create()->getKey()])
        ->assertHasFormErrors(['plan_id']);

    expect($reseller->activeSubscription()?->plan_id)->toBe($currentPlan->getKey());
});

it('schedules a cheaper plan for the end of the period through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $plan = Plan::factory()->active()->priced(6_000)->create();
    $current = Subscription::factory()->forSubscriber($reseller)->for($plan)->create(['price' => $plan->price, 'currency_code' => $plan->currency_code]);
    $cheaper = Plan::factory()->active()->priced(3_000)->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => $cheaper->getKey()])
        ->assertNotified(__('vendra-console::messages.plan_change_scheduled', ['plan' => $cheaper->name]));

    expect($reseller->subscriptions()->count())->toBe(1)
        ->and($current->refresh()->scheduled_plan_id)->toBe($cheaper->getKey());
});

it('refuses an upgrade the wallet cannot cover and charges it once credited', function (): void {
    actingConsoleAdmin();
    Queue::fake();
    TransactionGatewayFactory::new()->active()->internal()->create();

    $reseller = Reseller::factory()->active()->create();
    consoleResellerUserFor($reseller);
    $plan = Plan::factory()->active()->priced(3_000)->maxUnits(1)->create();
    Subscription::factory()->forSubscriber($reseller)->for($plan)->create(['price' => $plan->price, 'currency_code' => $plan->currency_code]);
    $upgrade = Plan::factory()->active()->priced(6_000)->maxUnits(5)->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => $upgrade->getKey()])
        ->assertNotified(__('vendra-console::messages.insufficient_wallet_balance'));

    expect($reseller->subscriptions()->count())->toBe(1);

    resolve(CreditResellerWalletAction::class)->execute($reseller, 6_000, $upgrade->currency_code, 'Bank transfer');

    livewire(ListResellers::class)
        ->callAction(TestAction::make('changePlan')->table($reseller), ['plan_id' => $upgrade->getKey()])
        ->assertNotified(__('vendra-console::messages.plan_change_pending_payment', ['plan' => $upgrade->name]));

    expect($reseller->subscriptions()->where('status', SubscriptionStatus::PendingPayment)->sole()->plan_id)->toBe($upgrade->getKey());
});

it('renews a lapsed subscription from where it ended through the table row action', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    $expired = Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->graceDays(5))->expired()->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller));

    $renewal = $reseller->subscriptions()->active()->sole();

    expect($renewal->isNot($expired))->toBeTrue()
        ->and($renewal->starts_at->equalTo($expired->ends_at))->toBeTrue();
});

it('refuses a renewal the wallet cannot cover', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    consoleResellerUserFor($reseller);
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->priced(3_000)->graceDays(5))->expired()->create();

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller))
        ->assertNotified(__('vendra-console::messages.insufficient_wallet_balance'));

    expect($reseller->subscriptions()->count())->toBe(1);
});

it('hides renewal while a subscription is running', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active())->create();

    livewire(ListResellers::class)
        ->assertActionHidden(TestAction::make('renew')->table($reseller));
});

it('blocks a renewal that cannot hold the current stores', function (): void {
    actingConsoleAdmin();

    $reseller = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->maxUnits(1))->expired()->create();
    createTestTenant(['reseller_id' => $reseller->getKey()]);
    createTestTenant(['reseller_id' => $reseller->getKey()]);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('renew')->table($reseller))
        ->assertNotified(__('vendra-console::messages.renewal_blocked'));

    expect($reseller->subscriptions()->count())->toBe(1);
});

it('flags and filters resellers whose plan includes priority support', function (): void {
    actingConsoleAdmin();

    $priority = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($priority)->for(Plan::factory()->active()->withFeatures(['priority_support']))->create();
    $standard = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($standard)->for(Plan::factory()->active())->create();

    livewire(ListResellers::class)
        ->loadTable()
        ->assertTableColumnStateSet('has_priority_support', true, $priority)
        ->assertTableColumnStateSet('has_priority_support', false, $standard)
        ->filterTable('priority_support')
        ->assertCanSeeTableRecords([$priority])
        ->assertCanNotSeeTableRecords([$standard]);
});

it('warns when lowering a plan leaves resellers over it and filters them in the reseller list', function (): void {
    actingConsoleAdmin();

    $plan = Plan::factory()->active()->maxUnits(5)->create();
    $over = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($over)->for($plan)->create();
    Store::factory()->count(2)->create(['reseller_id' => $over->getKey()]);
    $within = Reseller::factory()->active()->create();
    Subscription::factory()->forSubscriber($within)->for($plan)->create();
    Store::factory()->create(['reseller_id' => $within->getKey()]);

    livewire(EditPlan::class, ['record' => $plan->getKey()])
        ->fillForm(['max_units' => 1])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(trans_choice('vendra-console::messages.plan_leaves_resellers_over', 1, ['count' => 1]));

    livewire(ListResellers::class)
        ->loadTable()
        ->filterTable('over_plan')
        ->assertCanSeeTableRecords([$over])
        ->assertCanNotSeeTableRecords([$within]);
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
    'creditWallet',
]);

it('credits a reseller wallet through the table row action and shows the balance', function (): void {
    actingConsoleAdmin();
    TransactionGatewayFactory::new()->active()->internal()->create();

    $reseller = Reseller::factory()->active()->create();
    consoleResellerUserFor($reseller);
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->priced(3_000))->create(['currency_code' => 'USD']);

    livewire(ListResellers::class)
        ->callAction(TestAction::make('creditWallet')->table($reseller), [
            'amount' => 5_000,
            'currency_code' => 'usd',
            'note' => 'Bank transfer 1234',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($reseller->walletBalance('USD'))->toBe(5_000);

    livewire(ViewReseller::class, ['record' => $reseller->getKey()])
        ->assertSee('$50.00');
});

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
