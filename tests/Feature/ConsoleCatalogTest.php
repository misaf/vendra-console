<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\ListPlans;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\EditStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\ListStorefrontImages;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Models\StorefrontImage;
use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $consoleUser = User::factory()->create(['tenant_id' => null]);
    Console::factory()->for($consoleUser)->create();
    actingAs($consoleUser, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));
});

it('deactivates the default plan from the table and hands the default to another active plan', function (): void {
    $default = Plan::factory()->create(['active' => true]);
    $other = Plan::factory()->create(['active' => true]);

    expect($default->refresh()->is_default)->toBeTrue();

    livewire(ListPlans::class)
        ->loadTable()
        ->callAction(TestAction::make('deactivatePlan')->table($default))
        ->assertNotified(__('vendra-console::messages.deactivated'));

    expect($default->refresh()->active)->toBeFalse()
        ->and($default->is_default)->toBeFalse()
        ->and($other->refresh()->is_default)->toBeTrue();
});

it('shows a paid plan price in major units with its currency', function (): void {
    $plan = Plan::factory()->priced(1_500, 'USD')->create();

    livewire(ListPlans::class)
        ->loadTable()
        ->assertTableColumnStateSet('price', $plan->formattedPrice(), $plan)
        ->assertTableColumnStateNotSet('price', '1500 USD', $plan);
});

it('restores a deleted plan from the trashed filter without handing it the default', function (): void {
    $deleted = Plan::factory()->create(['active' => true]);
    $other = Plan::factory()->create(['active' => true]);
    resolve(DeletePlanAction::class)->execute($deleted);

    livewire(ListPlans::class)
        ->loadTable()
        ->filterTable('trashed', false)
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($deleted))
        ->callAction(TestAction::make(RestoreAction::class)->table($deleted))
        ->assertHasNoErrors();

    expect($deleted->refresh()->trashed())->toBeFalse()
        ->and($deleted->is_default)->toBeFalse()
        ->and($other->refresh()->is_default)->toBeTrue();
});

it('deactivates a storefront image from the table', function (): void {
    $image = StorefrontImage::factory()->create(['active' => true]);

    livewire(ListStorefrontImages::class)
        ->loadTable()
        ->callAction(TestAction::make('deactivateStorefrontImage')->table($image))
        ->assertNotified(__('vendra-console::messages.deactivated'));

    expect($image->refresh()->active)->toBeFalse();
});

it('deletes an unused storefront image from the list but not one a deployment uses', function (): void {
    $unused = StorefrontImage::factory()->create();
    $inUse = StorefrontDeployment::factory()->create()->storefrontImage;

    livewire(ListStorefrontImages::class)
        ->loadTable()
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($inUse))
        ->callAction(TestAction::make(DeleteAction::class)->table($unused))
        ->assertNotified();

    assertDatabaseMissing('storefront_images', ['id' => $unused->getKey()]);
    assertDatabaseHas('storefront_images', ['id' => $inUse->getKey()]);
});

it('reports a plan that came into use after the list loaded instead of deleting it', function (): void {
    $plan = Plan::factory()->create();
    $planBecameUsed = false;

    // Subscribe to the plan right before the delete checks it, after the list read it as unused.
    DB::beforeExecuting(function (string $query) use ($plan, &$planBecameUsed): void {
        if ($planBecameUsed || ! str_contains($query, '"subscriptions"') || str_contains($query, '"in_use"')) {
            return;
        }

        $planBecameUsed = true;
        Subscription::factory()->forSubscriber(Reseller::factory()->create())->for($plan)->create();
    });

    livewire(ListPlans::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($plan))
        ->assertNotified(__('vendra-console::messages.delete_blocked'));

    assertDatabaseHas('plans', ['id' => $plan->getKey(), 'deleted_at' => null]);
});

it('reports a storefront image that came into use after the list loaded instead of deleting it', function (): void {
    $image = StorefrontImage::factory()->create();
    $imageBecameUsed = false;

    // Deploy the image right before the delete checks it, after the list read it as unused.
    DB::beforeExecuting(function (string $query) use ($image, &$imageBecameUsed): void {
        if ($imageBecameUsed || ! str_contains($query, '"storefront_deployments"') || str_contains($query, '"in_use"')) {
            return;
        }

        $imageBecameUsed = true;
        StorefrontDeployment::factory()->for($image, 'storefrontImage')->create();
    });

    livewire(ListStorefrontImages::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($image))
        ->assertNotified(__('vendra-console::messages.delete_blocked'));

    assertDatabaseHas('storefront_images', ['id' => $image->getKey()]);
});

it('updates a storefront image from the edit page', function (): void {
    $image = StorefrontImage::factory()->create(['notes' => null]);

    livewire(EditStorefrontImage::class, ['record' => $image->getKey()])
        ->fillForm(['notes' => 'Retired after the spring release.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($image->refresh()->notes)->toBe('Retired after the spring release.');
});
