<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Misaf\VendraConsole\Filament\Widgets\ConsoleOverview;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraTenant\Enums\TenantProvisioningStatus;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://console-dashboard.test');
    Config::set('vendra-store.storefront.network', 'traefik-public');
    fakeDockerEngine();
});

function actAsConsoleUser(): User
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

describe('console dashboard intervention tracking', function (): void {
    it('shows stores needing attention as the first stat', function (): void {
        $failed = Store::factory()->provisioningFailed()->active()->create();
        $pending = Store::factory()->create([
            'provisioning_status' => TenantProvisioningStatus::Pending,
            'active' => false,
        ]);

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.stores_needing_attention'))
            ->assertSee('2');
    });

    it('shows zero intervention when all stores are active', function (): void {
        Store::factory()->count(3)->active()->create();

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.stores_needing_attention'))
            ->assertSee('0');
    });

    it('counts provisioning and failed stores together in the intervention stat', function (): void {
        Store::factory()->provisioningFailed()->active()->create();
        Store::factory()->provisioning()->active()->create();
        Store::factory()->active()->create();

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee('2');
    });
});

describe('console dashboard fleet totals', function (): void {
    it('shows total store count and active/suspended breakdown', function (): void {
        Store::factory()->count(3)->active()->create();
        Store::factory()->active()->suspended()->create();

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.fleet_totals'))
            ->assertSee(__('console.stores_active_suspended', [
                'active' => 3,
                'suspended' => 1,
            ]));
    });
});

describe('console dashboard reseller tracking', function (): void {
    it('counts resellers with active and total', function (): void {
        $active = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($active)->for(Plan::factory()->create())->create();
        Reseller::factory()->create(['active' => false]);

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.resellers'))
            ->assertSee('2');
    });
});

describe('console dashboard storefront tracking', function (): void {
    it('shows ready deployments count separately from failed', function (): void {
        $readyStore = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($readyStore)->create(['status' => StorefrontDeploymentStatus::Ready]);

        $failedStore = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($failedStore)->create(['status' => StorefrontDeploymentStatus::Failed]);

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.storefronts_ready'))
            ->assertSee('1')
            ->assertSee(__('console.failed_deployments'))
            ->assertSee('1');
    });

    it('shows processing deployments count in the ready stat description', function (): void {
        $readyStore = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($readyStore)->create(['status' => StorefrontDeploymentStatus::Ready]);

        $processingStore = Store::factory()->active()->create();
        StorefrontDeployment::factory()->for($processingStore)->create(['status' => StorefrontDeploymentStatus::Processing]);

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.deployments_processing').': 1');
    });
});

describe('console dashboard subscription health', function (): void {
    it('counts active subscriptions and those expiring within 7 days', function (): void {
        $resellerA = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($resellerA)->for(Plan::factory()->create())->create();

        $resellerB = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($resellerB)->for(Plan::factory()->create())
            ->create(['ends_at' => now()->addDays(5)]);

        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.active_subscriptions'))
            ->assertSee(__('console.expiring_soon'));
    });
});

describe('console dashboard empty state', function (): void {
    it('shows zeros for all stats when no data exists', function (): void {
        actAsConsoleUser();

        livewire(ConsoleOverview::class)
            ->assertOk()
            ->assertSee(__('console.stores_needing_attention'))
            ->assertSee('0')
            ->assertSee(__('console.fleet_totals'))
            ->assertSee(__('console.resellers'))
            ->assertSee(__('console.active_subscriptions'))
            ->assertSee('0')
            ->assertSee(__('console.expiring_soon'))
            ->assertSee('0');
    });
});
