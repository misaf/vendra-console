<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ListStorefrontDeployments;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ViewStorefrontDeployment;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraConsole\Filament\Widgets\ConsoleOverview;
use Misaf\VendraConsole\Filament\Widgets\ContainerRuntimeHealth;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Jobs\ProvisionStorefrontJob;
use Misaf\VendraStore\Jobs\ReconcileStorefrontJob;
use Misaf\VendraStore\Jobs\RestartStorefrontJob;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://console-runtime.test');
    Config::set('vendra-store.storefront.network', 'traefik-public');
});

function actAsOperationalConsoleUser(): User
{
    $consoleUser = User::factory()->create(['tenant_id' => null]);

    Console::factory()->for($consoleUser)->create();

    actingAs($consoleUser, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $consoleUser;
}

it('keeps deployment operations behind the console guard', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('console'));
    $url = StorefrontDeploymentResource::getUrl('index');

    $this->get($url)->assertRedirect();

    actAsOperationalConsoleUser();

    $this->get($url)->assertOk();
    expect(StorefrontDeploymentResource::canCreate())->toBeFalse();
});

it('lists and filters storefront deployments by status, store, and requested date', function (): void {
    $store = Store::factory()->active()->create();
    $failed = StorefrontDeployment::factory()->for($store)->create([
        'status' => StorefrontDeploymentStatus::Failed,
        'requested_at' => '2026-08-20 10:00:00',
    ]);
    $ready = StorefrontDeployment::factory()->create([
        'status' => StorefrontDeploymentStatus::Ready,
        'requested_at' => '2026-07-01 10:00:00',
    ]);

    actAsOperationalConsoleUser();

    livewire(ListStorefrontDeployments::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$failed, $ready])
        ->filterTable('status', StorefrontDeploymentStatus::Failed->value)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ready])
        ->resetTableFilters()
        ->filterTable('store_id', $store->getKey())
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ready])
        ->resetTableFilters()
        ->filterTable('requested_at', [
            'from' => '2026-08-01',
            'until' => '2026-08-31',
        ])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ready]);
});

it('retries only failed deployments through the existing provisioning job', function (): void {
    Queue::fake();
    $failed = StorefrontDeployment::factory()->for(Store::factory()->active())->create(['status' => StorefrontDeploymentStatus::Failed]);
    $ready = StorefrontDeployment::factory()->create(['status' => StorefrontDeploymentStatus::Ready]);

    actAsOperationalConsoleUser();

    livewire(ListStorefrontDeployments::class)
        ->assertActionVisible(TestAction::make('retryDeployment')->table($failed))
        ->assertActionHidden(TestAction::make('retryDeployment')->table($ready))
        ->callAction(TestAction::make('retryDeployment')->table($failed))
        ->assertNotified();

    Queue::assertPushed(
        ProvisionStorefrontJob::class,
        fn (ProvisionStorefrontJob $job): bool => $job->deploymentId === $failed->id && ! $job->force,
    );
});

it('queues reconcile and restart on the storefront worker and reads logs through the runtime contract', function (): void {
    Queue::fake();
    $deployment = StorefrontDeployment::factory()->for(Store::factory()->active())->create([
        'status' => StorefrontDeploymentStatus::Ready,
        'slug' => 'contract-operated',
    ]);
    $runtime = fakeExistingStorefront(logs: "booted\nready");

    actAsOperationalConsoleUser();

    livewire(ListStorefrontDeployments::class)
        ->call('loadTable')
        ->callAction(TestAction::make('reconcileDeployment')->table($deployment))
        ->assertNotified()
        ->callAction(TestAction::make('restartDeployment')->table($deployment))
        ->assertNotified();

    livewire(ListStorefrontDeployments::class)
        ->call('loadTable')
        ->mountAction(TestAction::make('viewLogs')->table($deployment))
        ->assertActionDataSet(['logs' => "booted\nready"]);

    Queue::assertPushed(ReconcileStorefrontJob::class, fn (ReconcileStorefrontJob $job): bool => $job->deploymentId === $deployment->id);
    Queue::assertPushed(RestartStorefrontJob::class, fn (RestartStorefrontJob $job): bool => $job->deploymentId === $deployment->id);

    expect($runtime->calls)->toContain('logs:vendra-storefront-contract-operated')
        ->not->toContain('restart');
});

it('degrades deployment inspection and actions when the runtime is unavailable', function (): void {
    $deployment = StorefrontDeployment::factory()->create([
        'status' => StorefrontDeploymentStatus::Ready,
        'slug' => 'unavailable-runtime',
    ]);
    bindFakeDockerEngine(fn ($request, bool $stream) => $stream
        ? dockerStreamResponse('', 500)
        : dockerResponse(['message' => 'The fake runtime is configured as unreachable.'], 500));

    actAsOperationalConsoleUser();

    livewire(ViewStorefrontDeployment::class, ['record' => $deployment->id])
        ->assertOk()
        ->assertSee('The fake runtime is configured as unreachable.');

    Queue::fake();

    livewire(ListStorefrontDeployments::class)
        ->callAction(TestAction::make('reconcileDeployment')->table($deployment))
        ->assertNotified();

    Queue::assertPushed(ReconcileStorefrontJob::class);
});

it('shows runtime and required network health without runtime-specific console logic', function (): void {
    $runtime = fakeExistingStorefront();

    actAsOperationalConsoleUser();

    livewire(ContainerRuntimeHealth::class)
        ->assertOk()
        ->assertSee('Docker')
        ->assertSee('traefik-public')
        ->assertSee(__('vendra-console::messages.network_available', ['driver' => 'bridge']));

    expect(collect($runtime->transport->requests)->contains(
        fn ($request): bool => str_ends_with($request->path, '/_ping'),
    ))->toBeTrue();
});

it('renders runtime health from a cache that only unserializes allow-listed classes', function (): void {
    fakeExistingStorefront();
    Config::set('cache.default', 'array');
    Config::set('cache.stores.array.serialize', true);
    Config::set('cache.serializable_classes', []);
    Cache::forgetDriver('array');

    actAsOperationalConsoleUser();

    livewire(ContainerRuntimeHealth::class)->assertOk();

    livewire(ContainerRuntimeHealth::class)
        ->assertOk()
        ->assertSee('Docker')
        ->assertSee(__('vendra-console::messages.network_available', ['driver' => 'bridge']));
});

it('probes the runtime once per cache window however many dashboards poll', function (): void {
    $runtime = fakeExistingStorefront();

    actAsOperationalConsoleUser();

    livewire(ContainerRuntimeHealth::class)->assertOk();
    $requestsAfterFirstRender = count($runtime->transport->requests);

    livewire(ContainerRuntimeHealth::class)
        ->assertOk()
        ->assertSee(__('vendra-console::messages.network_available', ['driver' => 'bridge']));

    expect($runtime->transport->requests)->toHaveCount($requestsAfterFirstRender);

    $this->travel(26)->seconds();

    livewire(ContainerRuntimeHealth::class)->assertOk();

    expect(count($runtime->transport->requests))->toBeGreaterThan($requestsAfterFirstRender);
});

it('links operational dashboard stats to resource filters', function (): void {
    actAsOperationalConsoleUser();

    $failedDeploymentsUrl = StorefrontDeploymentResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['value' => StorefrontDeploymentStatus::Failed->value],
        ],
    ]);
    $failedStoresUrl = StoreResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['values' => [
                StoreStatus::Failed->value,
                StoreStatus::Pending->value,
                StoreStatus::Provisioning->value,
            ]],
        ],
    ]);

    livewire(ConsoleOverview::class)
        ->assertOk()
        ->assertSeeHtml('href="'.e($failedDeploymentsUrl).'"')
        ->assertSeeHtml('href="'.e($failedStoresUrl).'"');
});

describe('store row storefront operations', function (): void {
    it('offers a retry on the store row only while its storefront has failed', function (): void {
        Queue::fake();
        $failedStore = Store::factory()->active()->create();
        $failed = StorefrontDeployment::factory()->for($failedStore)->create([
            'status' => StorefrontDeploymentStatus::Failed,
        ]);
        $readyStore = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($readyStore)->create([
            'status' => StorefrontDeploymentStatus::Ready,
        ]);

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->assertActionHidden(TestAction::make('retryStorefront')->table($readyStore))
            ->assertActionVisible(TestAction::make('retryStorefront')->table($failedStore))
            ->callAction(TestAction::make('retryStorefront')->table($failedStore))
            ->assertNotified();

        Queue::assertPushed(
            ProvisionStorefrontJob::class,
            fn (ProvisionStorefrontJob $job): bool => $job->deploymentId === $failed->id,
        );
    });

    it('queues a forced redeployment from the store row', function (): void {
        Queue::fake();
        $store = Store::factory()->active()->create();
        $deployment = StorefrontDeployment::factory()->for($store)->create([
            'status' => StorefrontDeploymentStatus::Ready,
        ]);

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->callAction(TestAction::make('redeployStorefront')->table($store))
            ->assertNotified();

        Queue::assertPushed(
            ProvisionStorefrontJob::class,
            fn (ProvisionStorefrontJob $job): bool => $job->deploymentId === $deployment->id && $job->force,
        );
    });

    it('queues restart and reconcile of a store storefront on the storefront worker', function (): void {
        Queue::fake();
        $store = Store::factory()->active()->create();
        $deployment = StorefrontDeployment::factory()->for($store)->create([
            'status' => StorefrontDeploymentStatus::Ready,
            'slug' => 'store-row-operated',
        ]);
        $runtime = fakeExistingStorefront();

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->callAction(TestAction::make('restartStorefront')->table($store))
            ->assertNotified()
            ->callAction(TestAction::make('reconcileStorefront')->table($store))
            ->assertNotified();

        Queue::assertPushed(RestartStorefrontJob::class, fn (RestartStorefrontJob $job): bool => $job->deploymentId === $deployment->id);
        Queue::assertPushed(ReconcileStorefrontJob::class, fn (ReconcileStorefrontJob $job): bool => $job->deploymentId === $deployment->id);
        expect($runtime->calls)->not->toContain('restart');
    });

    it('hides storefront run operations for a suspended store', function (): void {
        $store = Store::factory()->active()->suspended()->create();
        StorefrontDeployment::factory()->for($store)->create([
            'status' => StorefrontDeploymentStatus::Failed,
            'desired_state' => StorefrontDesiredState::Stopped,
        ]);

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->assertActionHidden(TestAction::make('startStorefront')->table($store))
            ->assertActionHidden(TestAction::make('restartStorefront')->table($store))
            ->assertActionHidden(TestAction::make('redeployStorefront')->table($store))
            ->assertActionHidden(TestAction::make('retryStorefront')->table($store));
    });

    it('reads storefront logs from the store row', function (): void {
        $store = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($store)->create([
            'status' => StorefrontDeploymentStatus::Ready,
            'slug' => 'store-row-logs',
        ]);
        fakeExistingStorefront(logs: "booted\nserving");

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->mountAction(TestAction::make('viewStorefrontLogs')->table($store))
            ->assertActionDataSet(['logs' => "booted\nserving"]);
    });

    it('links the store row to its latest deployment record', function (): void {
        $store = Store::factory()->active()->create();
        $deployment = StorefrontDeployment::factory()->for($store)->create(['status' => StorefrontDeploymentStatus::Ready]);

        actAsOperationalConsoleUser();

        livewire(ListStores::class)
            ->call('loadTable')
            ->assertActionHasUrl(
                TestAction::make('viewDeployment')->table($store),
                StorefrontDeploymentResource::getUrl('view', ['record' => $deployment]),
            );
    });
});
