<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\CreatePlan;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\ListPlans;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ListStorefrontDeployments;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Enums\StorefrontRuntimeState;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://provisioner:8080');
    fakeDockerEngine();
});

function actAsLabellingConsoleUser(): User
{
    $consoleUser = User::factory()->create(['tenant_id' => null]);

    ConsoleUser::factory()->for($consoleUser)->create();

    actingAs($consoleUser, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $consoleUser;
}

it('shows the storefront image reference in the deployment list', function (): void {
    actAsLabellingConsoleUser();

    $deployment = StorefrontDeployment::factory()->create();

    livewire(ListStorefrontDeployments::class)
        ->loadTable()
        ->assertTableColumnStateSet('storefrontImage.image', $deployment->storefrontImage->image, $deployment);
});

it('labels the reseller list name column as the name', function (): void {
    actAsLabellingConsoleUser();

    livewire(ListResellers::class)
        ->assertTableColumnExists('name', fn (TextColumn $column): bool => $column->getLabel() === __('vendra-console::attributes.name'));
});

it('translates store and storefront statuses in the store list', function (): void {
    actAsLabellingConsoleUser();

    $store = Store::factory()->active()->create();
    StorefrontDeployment::factory()->for($store)->create(['status' => StorefrontDeploymentStatus::Failed]);
    $storeStatus = Store::query()->findOrFail($store->getKey())->status()->value;

    livewire(ListStores::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('status', __("vendra-console::attributes.store_status_{$storeStatus}"), $store)
        ->assertTableColumnFormattedStateSet('storefront_status', __('vendra-console::attributes.deployment_status_failed'), $store);
});

it('translates plan period units in the list and the form', function (): void {
    actAsLabellingConsoleUser();

    $plan = Plan::factory()->period(PeriodUnit::Month, 3)->create();

    livewire(ListPlans::class)
        ->loadTable()
        ->assertTableColumnStateSet('period', '3 '.__('vendra-console::attributes.period_month'), $plan);

    $expectedOptions = collect(PeriodUnit::cases())
        ->mapWithKeys(fn (PeriodUnit $unit): array => [$unit->value => __("vendra-console::attributes.period_{$unit->value}")])
        ->all();

    livewire(CreatePlan::class)
        ->assertFormFieldExists('period_unit', fn (Select $field): bool => $field->getOptions() === $expectedOptions);
});

it('translates every enum-derived console label', function (string $locale): void {
    $prefixedEnums = [
        'deployment_status_' => StorefrontDeploymentStatus::cases(),
        'desired_state_' => StorefrontDesiredState::cases(),
        'runtime_state_' => StorefrontRuntimeState::cases(),
        'store_status_' => StoreStatus::cases(),
        'status_' => SubscriptionStatus::cases(),
        'period_' => PeriodUnit::cases(),
    ];

    $missing = [];

    foreach ($prefixedEnums as $prefix => $cases) {
        foreach ($cases as $case) {
            if (! Lang::has("vendra-console::attributes.{$prefix}{$case->value}", $locale, false)) {
                $missing[] = "{$prefix}{$case->value}";
            }
        }
    }

    expect($missing)->toBeEmpty();
})->with(['en', 'fa', 'de']);

it('carries every English package string into each translation', function (string $package, string $locale): void {
    $langPath = base_path("packages/{$package}/resources/lang");
    $missing = [];

    foreach (glob("{$langPath}/en/*.php") as $englishFile) {
        $file = basename($englishFile);
        $translatedFile = "{$langPath}/{$locale}/{$file}";
        $translated = is_file($translatedFile) ? require $translatedFile : [];

        $missingKeys = collect(require $englishFile)->dot()->keys()->diff(collect($translated)->dot()->keys());

        foreach ($missingKeys as $key) {
            $missing[] = basename($file, '.php').".{$key}";
        }
    }

    expect($missing)->toBeEmpty();
})->with(['vendra-console', 'vendra-reseller', 'vendra-store'])->with(['fa', 'de']);
