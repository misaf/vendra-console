<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ListStorefrontDeployments;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ViewStorefrontDeployment;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraConsole\Filament\Widgets\ConsoleOverview;
use Misaf\VendraConsole\Filament\Widgets\ContainerRuntimeHealth;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Jobs\ProvisionStorefrontJob;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://console-runtime.test');
    Config::set('vendra-store.storefront.network', 'traefik-public');
});

function actAsOperationalConsoleOperator(): ConsoleUser
{
    $operator = ConsoleUser::factory()->create();
    actingAs($operator, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $operator;
}

it('keeps deployment operations behind the console guard', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('console'));
    $url = StorefrontDeploymentResource::getUrl('index');

    $this->get($url)->assertRedirect();

    actAsOperationalConsoleOperator();

    $this->get($url)->assertOk();
    expect(StorefrontDeploymentResource::canCreate())->toBeFalse();
});

it('lists and filters storefront deployments by status, store, and requested date', function (): void {
    $store = Store::factory()->active()->create();
    $failed = StorefrontDeployment::factory()->for($store)->create([
        'status'       => StorefrontDeploymentStatus::Failed,
        'requested_at' => '2026-08-20 10:00:00',
    ]);
    $ready = StorefrontDeployment::factory()->create([
        'status'       => StorefrontDeploymentStatus::Ready,
        'requested_at' => '2026-07-01 10:00:00',
    ]);

    actAsOperationalConsoleOperator();

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
            'from'  => '2026-08-01',
            'until' => '2026-08-31',
        ])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ready]);
});

it('retries only failed deployments through the existing provisioning job', function (): void {
    Queue::fake();
    $failed = StorefrontDeployment::factory()->create(['status' => StorefrontDeploymentStatus::Failed]);
    $ready = StorefrontDeployment::factory()->create(['status' => StorefrontDeploymentStatus::Ready]);

    actAsOperationalConsoleOperator();

    livewire(ListStorefrontDeployments::class)
        ->assertActionVisible(TestAction::make('retryDeployment')->table($failed))
        ->assertActionHidden(TestAction::make('retryDeployment')->table($ready))
        ->callAction(TestAction::make('retryDeployment')->table($failed))
        ->assertNotified();

    Queue::assertPushed(
        ProvisionStorefrontJob::class,
        fn(ProvisionStorefrontJob $job): bool => $job->deploymentId === $failed->id && ! $job->force,
    );
});

it('reconciles, restarts, and reads logs through storefront and runtime contracts', function (): void {
    $deployment = StorefrontDeployment::factory()->create([
        'status' => StorefrontDeploymentStatus::Ready,
        'slug'   => 'contract-operated',
    ]);
    $runtime = fakeExistingStorefront(logs: "booted\nready");

    actAsOperationalConsoleOperator();

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

    expect($runtime->calls)->toContain(
        'restart',
        'logs:vendra-storefront-contract-operated',
    );
});

it('degrades deployment inspection and actions when the runtime is unavailable', function (): void {
    $deployment = StorefrontDeployment::factory()->create([
        'status' => StorefrontDeploymentStatus::Ready,
        'slug'   => 'unavailable-runtime',
    ]);
    bindFakeDockerEngine(fn($request, bool $stream) => $stream
        ? dockerStreamResponse('', 500)
        : dockerResponse(['message' => 'The fake runtime is configured as unreachable.'], 500));

    actAsOperationalConsoleOperator();

    livewire(ViewStorefrontDeployment::class, ['record' => $deployment->id])
        ->assertOk()
        ->assertSee('The fake runtime is configured as unreachable.');

    livewire(ListStorefrontDeployments::class)
        ->callAction(TestAction::make('reconcileDeployment')->table($deployment))
        ->assertNotified();
});

it('shows runtime and required network health without runtime-specific console logic', function (): void {
    $runtime = fakeExistingStorefront();

    actAsOperationalConsoleOperator();

    livewire(ContainerRuntimeHealth::class)
        ->assertOk()
        ->assertSee('Docker')
        ->assertSee('traefik-public')
        ->assertSee(__('console.network_available', ['driver' => 'bridge']));

    expect(collect($runtime->transport->requests)->contains(
        fn($request): bool => str_ends_with($request->path, '/_ping'),
    ))->toBeTrue();
});

it('links operational dashboard stats to resource filters', function (): void {
    actAsOperationalConsoleOperator();

    $failedDeploymentsUrl = StorefrontDeploymentResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['value' => StorefrontDeploymentStatus::Failed->value],
        ],
    ]);
    $failedStoresUrl = StoreResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['values' => [StoreStatus::Failed->value]],
        ],
    ]);

    livewire(ConsoleOverview::class)
        ->assertOk()
        ->assertSeeHtml('href="' . e($failedDeploymentsUrl) . '"')
        ->assertSeeHtml('href="' . e($failedStoresUrl) . '"');
});
