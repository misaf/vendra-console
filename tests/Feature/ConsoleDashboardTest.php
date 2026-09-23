<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraConsole\Filament\Pages\Dashboard;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\ActivityLogResource;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Widgets\ContainerRuntimeHealth;
use Misaf\VendraConsole\Filament\Widgets\NeedsAttention;
use Misaf\VendraConsole\Filament\Widgets\PlatformGrowthChart;
use Misaf\VendraConsole\Filament\Widgets\PlatformMetrics;
use Misaf\VendraConsole\Filament\Widgets\RecentActivity;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSubscription\Enums\SubscriptionPaymentStatus;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Models\SubscriptionPayment;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
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

    Console::factory()->active()->for($admin)->create();

    actingAs($admin, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $admin;
}

describe('console dashboard page', function (): void {
    it('lays out its widgets by urgency', function (): void {
        expect(new Dashboard()->getWidgets())->toBe([
            NeedsAttention::class,
            PlatformMetrics::class,
            PlatformGrowthChart::class,
            ContainerRuntimeHealth::class,
            RecentActivity::class,
        ]);
    });

    it('renders for a console user', function (): void {
        actAsConsoleUser();

        $this->get(route('filament.console.pages.dashboard'))->assertOk();
    });
});

describe('needs attention', function (): void {
    it('collapses to an all-clear stat when nothing needs a console user', function (): void {
        Store::factory()->count(2)->active()->create();

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSee(__('vendra-console::attributes.needs_attention_all_clear'))
            ->assertDontSee(__('vendra-console::attributes.stores_needing_attention'));
    });

    it('counts unfinished and failed stores together', function (): void {
        Store::factory()->provisioningFailed()->active()->create();
        Store::factory()->provisioning()->active()->create();
        Store::factory()->provisioningPending()->active()->create();
        Store::factory()->active()->create();

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSeeInOrder([__('vendra-console::attributes.stores_needing_attention'), '3'])
            ->assertDontSee(__('vendra-console::attributes.needs_attention_all_clear'));
    });

    it('shows failed deployments, past-due subscriptions and subscriptions ending this week', function (): void {
        StorefrontDeployment::factory()->for(Store::factory()->active())->create(['status' => StorefrontDeploymentStatus::Failed]);
        Subscription::factory()->forSubscriber(Reseller::factory()->active()->create())->for(Plan::factory()->active())->create([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => now()->subDay(),
        ]);
        Subscription::factory()->forSubscriber(Reseller::factory()->active()->create())->for(Plan::factory()->active())->create([
            'ends_at' => now()->addDays(3),
            'expiry_reminder_sent_at' => now(),
        ]);

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSee(__('vendra-console::attributes.failed_deployments'))
            ->assertSee(__('vendra-console::attributes.past_due_subscriptions'))
            ->assertSee(__('vendra-console::attributes.expiring_soon'))
            ->assertSeeHtml('href="'.e(ResellerResource::getUrl('index', [
                'tableFilters' => ['subscription_health' => ['value' => 'past_due']],
            ])).'"');
    });

    it('shows payments a person has to resolve', function (): void {
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::NeedsReconciliation]);
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::RequiresAction]);
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::Paid]);

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSeeInOrder([__('vendra-console::attributes.payments_needing_review'), '2']);
    });

    it('shows jobs that failed within the last day only', function (): void {
        foreach ([now()->subHours(2), now()->subDays(2)] as $failedAt) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'redis',
                'queue' => 'storefronts',
                'payload' => '{}',
                'exception' => 'RuntimeException',
                'failed_at' => $failedAt,
            ]);
        }

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertSeeInOrder([__('vendra-console::attributes.failed_jobs'), '1']);
    });

    it('reports no failed jobs when failures are not stored in the database', function (): void {
        Config::set('queue.failed.driver', 'null');
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'storefronts',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now()->subHour(),
        ]);

        actAsConsoleUser();

        livewire(NeedsAttention::class)
            ->assertOk()
            ->assertDontSee(__('vendra-console::attributes.failed_jobs'));
    });

    it('lists resellers with a past-due subscription through the linked filter', function (): void {
        $pastDue = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($pastDue)->for(Plan::factory()->active())->create([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => now()->subDay(),
        ]);
        $current = Reseller::factory()->active()->create();
        Subscription::factory()->forSubscriber($current)->for(Plan::factory()->active())->create();

        actAsConsoleUser();

        livewire(ListResellers::class)
            ->call('loadTable')
            ->filterTable('subscription_health', 'past_due')
            ->assertCanSeeTableRecords([$pastDue])
            ->assertCanNotSeeTableRecords([$current]);
    });
});

describe('platform metrics', function (): void {
    it('shows store, reseller and subscription totals', function (): void {
        Store::factory()->count(3)->active()->create();
        Store::factory()->active()->suspended()->create();
        Reseller::factory()->active()->create();
        Reseller::factory()->create(['active' => false]);
        Subscription::factory()->forSubscriber(Reseller::factory()->active()->create())->for(Plan::factory()->active())->create();
        Subscription::factory()->expired()->forSubscriber(Reseller::factory()->active()->create())->for(Plan::factory()->active())->create();

        actAsConsoleUser();

        livewire(PlatformMetrics::class)
            ->assertOk()
            ->assertSee(__('vendra-console::attributes.stores_active_suspended', ['active' => 3, 'suspended' => 1]))
            ->assertSee(__('vendra-console::attributes.resellers_total', ['count' => Reseller::query()->count()]))
            ->assertSeeInOrder([__('vendra-console::attributes.active_subscriptions'), '1']);
    });

    it("sums this month's and last month's paid revenue per currency", function (): void {
        $this->travelTo(now()->setDate(2026, 9, 17));

        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::Paid, 'amount' => 1_500, 'currency_code' => 'USD', 'paid_at' => now()->subDays(2)]);
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::Paid, 'amount' => 2_500, 'currency_code' => 'USD', 'paid_at' => now()->subDay()]);
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::Pending, 'amount' => 9_900, 'currency_code' => 'USD']);
        SubscriptionPayment::factory()->create(['status' => SubscriptionPaymentStatus::Paid, 'amount' => 1_000, 'currency_code' => 'USD', 'paid_at' => now()->subMonth()]);

        actAsConsoleUser();

        livewire(PlatformMetrics::class)
            ->assertOk()
            ->assertSeeInOrder([__('vendra-console::attributes.revenue_this_month'), '$40.00'])
            ->assertSee(__('vendra-console::attributes.revenue_last_month', ['amount' => '$10.00']));
    });

    it('says there is no revenue before anything is paid', function (): void {
        actAsConsoleUser();

        livewire(PlatformMetrics::class)
            ->assertOk()
            ->assertSee(__('vendra-console::attributes.no_revenue'));
    });
});

describe('platform growth chart', function (): void {
    it('plots one point per day for the chosen window', function (int $days): void {
        Store::factory()->count(2)->active()->create();

        actAsConsoleUser();

        $component = livewire(PlatformGrowthChart::class)
            ->set('filter', (string) $days)
            ->assertOk();

        $data = (fn (): array => $this->getData())->call($component->instance());

        expect(Arr::get($data, 'labels'))->toHaveCount($days)
            ->and(Arr::get($data, 'datasets'))->toHaveCount(3)
            ->and(Arr::last(Arr::array($data, 'datasets.0.data')))->toBe(2.0);
    })->with([7, 30, 90]);

    it('keeps an offboarded store on the day it was created', function (): void {
        Store::factory()->active()->create()->delete();

        actAsConsoleUser();

        $component = livewire(PlatformGrowthChart::class)->set('filter', '7');

        $data = (fn (): array => $this->getData())->call($component->instance());

        expect(Arr::last(Arr::array($data, 'datasets.0.data')))->toBe(1.0);
    });

    it('names the days in the console locale', function (): void {
        $this->travelTo(Date::parse('2026-03-15 12:00:00'));
        App::setLocale('de');

        actAsConsoleUser();

        $component = livewire(PlatformGrowthChart::class)->set('filter', '7');

        $data = (fn (): array => $this->getData())->call($component->instance());

        expect(Arr::last(Arr::array($data, 'labels')))->toBe('Mär 15');
    });
});

describe('recent activity', function (): void {
    it('lists the latest audit entries and links to the full log', function (): void {
        $store = Store::factory()->active()->create();
        $entries = collect(range(1, 10))->map(fn (int $number): ActivityLog => ActivityLog::query()->create([
            'tenant_id' => $store->getKey(),
            'log_name' => 'default',
            'description' => "Platform change {$number}",
            'event' => 'updated',
        ]));

        actAsConsoleUser();

        livewire(RecentActivity::class)
            ->call('loadTable')
            ->assertOk()
            ->assertCanSeeTableRecords($entries->slice(2))
            ->assertCanNotSeeTableRecords($entries->take(2))
            ->assertSeeHtml('href="'.e(ActivityLogResource::getUrl('index')).'"');
    });
});
