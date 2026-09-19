<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraConsole\Filament\Pages\ManagePlatformSettings;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\ActivityLogResource;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\EditStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraConsole\Filament\Widgets\NeedsAttention;
use Misaf\VendraConsole\Filament\Widgets\PlatformMetrics;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Jobs\CompleteStoreProvisioningJob;
use Misaf\VendraStore\Jobs\ProvisionStorefrontJob;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Settings\StoreCreationSettings;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveToggleColumn;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraTenant\Enums\TenantProvisioningStatus;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://provisioner:8080');
    fakeDockerEngine();
});

/**
 * Act as a console user; named apart from ConsolePanelTest's helper.
 */
function actAsPlatformUser(): User
{
    $admin = User::factory()->create(['tenant_id' => null]);

    Console::factory()->active()->for($admin)->create();

    actingAs($admin, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $admin;
}

describe('assigning stores to resellers', function (): void {
    it('moves a store to another reseller within its plan', function (): void {
        $from = Reseller::factory()->active()->create();
        $to = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($to)->for(Plan::factory()->active()->maxUnits(2))->create();
        $store = Store::factory()->create(['reseller_id' => $from->getKey()]);

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('assignReseller')->table($store), [
                'reseller_id' => $to->getKey(),
            ])
            ->assertHasNoErrors();

        expect($store->fresh()?->reseller_id)->toBe($to->getKey());
    });

    it('takes a store back for the platform when no reseller is chosen', function (): void {
        $reseller = Reseller::factory()->active()->create();
        $store = Store::factory()->create(['reseller_id' => $reseller->getKey()]);

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('assignReseller')->table($store), ['reseller_id' => null])
            ->assertHasNoErrors();

        expect($store->fresh()?->reseller_id)->toBeNull();
    });

    /*
     | Reassignment consumes a slot in the receiving plan, so it has to fail the
     | same way creation does rather than writing the column past the check.
     */
    it('refuses a reseller whose plan is already full', function (): void {
        $to = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($to)->for(Plan::factory()->active()->maxUnits(1))->create();
        Store::factory()->create(['reseller_id' => $to->getKey()]);

        $store = Store::factory()->create(['reseller_id' => null]);

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('assignReseller')->table($store), [
                'reseller_id' => $to->getKey(),
            ])
            ->assertNotified();

        expect($store->fresh()?->reseller_id)->toBeNull();
    });

    it('leaves a store with the reseller it already has without spending a slot', function (): void {
        $reseller = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->active()->maxUnits(1))->create();
        $store = Store::factory()->create(['reseller_id' => $reseller->getKey()]);

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('assignReseller')->table($store), [
                'reseller_id' => $reseller->getKey(),
            ])
            ->assertHasNoErrors();

        expect($store->fresh()?->reseller_id)->toBe($reseller->getKey());
    });

    it('reassigns a store from the edit page header action', function (): void {
        $from = Reseller::factory()->active()->create();
        $to = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($to)->for(Plan::factory()->active()->maxUnits(2))->create();
        $store = Store::factory()->create(['reseller_id' => $from->getKey()]);

        actAsPlatformUser();

        livewire(EditStore::class, ['record' => $store->getKey()])
            ->callAction('assignReseller', ['reseller_id' => $to->getKey()])
            ->assertHasNoErrors();

        expect($store->fresh()?->reseller_id)->toBe($to->getKey());
    });
});

describe('operating store lifecycles', function (): void {
    it('suspends and reactivates a store through domain-backed table actions', function (): void {
        $store = Store::factory()->active()->create();

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('suspendStore')->table($store))
            ->assertHasNoErrors();

        expect($store->fresh()?->active)->toBeFalse();

        livewire(ListStores::class)
            ->callAction(TestAction::make('reactivateStore')->table($store->fresh()))
            ->assertHasNoErrors();

        expect($store->fresh()?->active)->toBeTrue();
    });

    it('suspends and reactivates a store from the active toggle column', function (): void {
        $store = Store::factory()->active()->create();

        actAsPlatformUser();

        livewire(ListStores::class)
            ->call('updateTableColumnState', 'active', (string) $store->getKey(), false)
            ->assertNotified(__('vendra-console::messages.store_suspended'));

        expect($store->fresh()?->active)->toBeFalse();

        livewire(ListStores::class)
            ->call('updateTableColumnState', 'active', (string) $store->getKey(), true)
            ->assertNotified(__('vendra-console::messages.store_reactivated'));

        expect($store->fresh()?->active)->toBeTrue();
    });

    it('disables the active toggle for a store that has not finished provisioning', function (): void {
        $store = Store::factory()->inactive()->provisioningPending()->create();

        actAsPlatformUser();

        livewire(ListStores::class)
            ->loadTable()
            ->assertTableColumnExists('active', fn (IsActiveToggleColumn $column): bool => $column->isDisabled(), $store);
    });

    it('queues the existing provisioning recovery job for a failed store', function (): void {
        Queue::fake();
        $store = Store::factory()->provisioningFailed()->create();

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('retryStoreProvisioning')->table($store))
            ->assertHasNoErrors();

        expect($store->fresh()?->provisioning_status)->toBe(TenantProvisioningStatus::Pending);
        Queue::assertPushed(
            CompleteStoreProvisioningJob::class,
            fn (CompleteStoreProvisioningJob $job): bool => $job->tenantId === $store->getKey(),
        );
    });

    it('stops and starts a storefront through its domain lifecycle actions', function (): void {
        $store = Store::factory()->active()->create();
        $deployment = StorefrontDeployment::factory()->for($store)->create([
            'status' => StorefrontDeploymentStatus::Ready,
            'desired_state' => StorefrontDesiredState::Running,
            'slug' => 'acme-flowers',
            'domain' => 'acme.test',
            'configuration' => [
                'slug' => 'acme-flowers',
                'domain' => 'acme.test',
                'siteUrl' => 'https://acme.test',
                'businessType' => 'Florist',
                'priceCurrency' => 'IRR',
                'name' => ['en' => 'Acme Flowers'],
                'address' => ['locality' => 'Tehran', 'country' => 'IR'],
                'contact' => [
                    'mobilePhone' => '09120000000',
                    'officePhone' => '02100000000',
                    'email' => 'contact@acme.test',
                    'hoursOpen' => '08:00',
                    'hoursClose' => '21:00',
                    'mapQuery' => '35.7,51.4',
                ],
                'social' => [
                    'whatsappPhone' => '+989120000000',
                    'telegramUsername' => 'acmeflowers',
                    'instagramUsername' => 'acmeflowers',
                ],
            ],
        ]);
        app()->call([new ProvisionStorefrontJob($deployment->id, force: true), 'handle']);

        actAsPlatformUser();

        livewire(ListStores::class)
            ->callAction(TestAction::make('stopStorefront')->table($store))
            ->assertHasNoErrors();

        expect($deployment->fresh()?->desired_state)->toBe(StorefrontDesiredState::Stopped);

        livewire(ListStores::class)
            ->callAction(TestAction::make('startStorefront')->table($store->fresh()))
            ->assertHasNoErrors();

        expect($deployment->fresh()?->desired_state)->toBe(StorefrontDesiredState::Running);
    });
});

describe('platform settings', function (): void {
    it('brands the console from the platform settings', function (): void {
        resolve(ConsoleSettings::class)->fill(['platform_name' => 'Acme Operations'])->save();

        expect(Filament::getPanel('console')->getBrandName())->toBe('Acme Operations');
    });

    it('freezes console store creation from the platform setting', function (): void {
        resolve(StoreCreationSettings::class)->fill(['open' => false])->save();

        expect(StoreResource::canCreate())->toBeFalse();

        resolve(StoreCreationSettings::class)->fill(['open' => true])->save();

        expect(StoreResource::canCreate())->toBeTrue();
    });

    /*
     | Opened on a database no one has touched beyond migrating: the settings
     | migration is what keeps this from throwing `MissingSettings`, and there
     | is no tenant in this panel to fall back on.
     */
    it('lets a console user close store creation from the platform settings page', function (): void {
        actAsPlatformUser();

        livewire(ManagePlatformSettings::class)
            ->assertFormSet(['open' => true])
            ->fillForm(['open' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(StoreCreationSettings::class)->open)->toBeFalse();
    });

    it('lets a console user rename the platform from the platform settings page', function (): void {
        actAsPlatformUser();

        livewire(ManagePlatformSettings::class)
            ->assertFormSet(['platform_name' => 'Vendra Console'])
            ->fillForm(['platform_name' => 'Acme Platform'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(ConsoleSettings::class)->platform_name)->toBe('Acme Platform')
            ->and(resolve(StoreCreationSettings::class)->open)->toBeTrue();
    });

    it('requires a platform name', function (): void {
        actAsPlatformUser();

        livewire(ManagePlatformSettings::class)
            ->fillForm(['platform_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['platform_name' => 'required']);

        expect(resolve(ConsoleSettings::class)->platform_name)->toBe('Vendra Console');
    });

    it('rejects a non-boolean store creation state', function (): void {
        actAsPlatformUser();

        livewire(ManagePlatformSettings::class)
            ->fillForm(['open' => 'maybe'])
            ->call('save')
            ->assertHasFormErrors(['open']);

        expect(resolve(StoreCreationSettings::class)->open)->toBeTrue();
    });

    it('keeps the platform settings page behind the console guard', function (): void {
        $url = ManagePlatformSettings::getUrl(panel: 'console');

        $this->get($url)->assertRedirect();

        actAsPlatformUser();

        $this->get($url)->assertOk();
    });
});

describe('platform dashboard', function (): void {
    it('counts stores by status and storefronts by deployment state', function (): void {
        $active = Store::factory()->count(2)->active()->create();
        Store::factory()->active()->suspended()->create();
        Store::factory()->provisioning()->active()->create();
        Store::factory()->provisioningFailed()->active()->create();

        /*
         | Attached to stores this test created: the deployment factory would
         | otherwise make its own store, and a store with a random `active`
         | would move the counts under assertion.
         */
        StorefrontDeployment::factory()->for($active->first())->create(['status' => StorefrontDeploymentStatus::Ready]);
        StorefrontDeployment::factory()->for($active->last())->create(['status' => StorefrontDeploymentStatus::Failed]);

        actAsPlatformUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSeeInOrder([__('vendra-console::attributes.stores_needing_attention'), '2'])
            ->assertSee(__('vendra-console::attributes.stores_needing_attention_description'))
            ->assertSeeInOrder([__('vendra-console::attributes.failed_deployments'), '1']);

        livewire(PlatformMetrics::class)
            ->assertOk()
            ->assertSee(__('vendra-console::attributes.stores_active_suspended', ['active' => 2, 'suspended' => 1]));
    });
});

describe('activity visibility', function (): void {
    it('shows a console user activity from every store', function (): void {
        $storeA = Store::factory()->active()->create(['name' => 'Alpha Store']);
        $storeB = Store::factory()->active()->create(['name' => 'Beta Store']);

        $forA = ActivityLog::query()->create([
            'tenant_id' => $storeA->getKey(),
            'log_name' => 'default',
            'description' => 'Alpha changed a product',
            'event' => 'updated',
        ]);
        $forB = ActivityLog::query()->create([
            'tenant_id' => $storeB->getKey(),
            'log_name' => 'default',
            'description' => 'Beta changed a product',
            'event' => 'updated',
        ]);

        actAsPlatformUser();

        livewire(ListActivityLogs::class)
            ->call('loadTable')
            ->assertCanSeeTableRecords([$forA, $forB])
            ->assertSee('Alpha Store')
            ->assertSee('Beta Store');
    });

    it('names and filters by an offboarded store without listing every store up front', function (): void {
        $offboarded = Store::factory()->active()->create(['name' => 'Gamma Store']);
        $other = Store::factory()->active()->create(['name' => 'Delta Store']);
        $forOffboarded = ActivityLog::query()->create([
            'tenant_id' => $offboarded->getKey(),
            'log_name' => 'default',
            'description' => 'Gamma changed a product',
        ]);
        $forOther = ActivityLog::query()->create([
            'tenant_id' => $other->getKey(),
            'log_name' => 'default',
            'description' => 'Delta changed a product',
        ]);
        $offboarded->delete();

        actAsPlatformUser();

        livewire(ListActivityLogs::class)
            ->call('loadTable')
            ->assertTableColumnStateSet('store', 'Gamma Store', $forOffboarded)
            ->filterTable('tenant_id', $offboarded->getKey())
            ->assertCanSeeTableRecords([$forOffboarded])
            ->assertCanNotSeeTableRecords([$forOther]);
    });

    /*
     | The audit trail is a record of what happened. A console user holds no
     | tenant permissions, so the read is granted by panel access — and every
     | write stays closed regardless.
     */
    it('is read-only', function (): void {
        $store = Store::factory()->active()->create();
        $entry = ActivityLog::query()->create([
            'tenant_id' => $store->getKey(),
            'log_name' => 'default',
            'description' => 'Something happened',
        ]);

        expect(ActivityLogResource::canViewAny())->toBeTrue()
            ->and(ActivityLogResource::canCreate())->toBeFalse()
            ->and(ActivityLogResource::canEdit($entry))->toBeFalse()
            ->and(ActivityLogResource::canDelete($entry))->toBeFalse();
    });
});
