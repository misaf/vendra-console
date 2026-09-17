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
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StorefrontRuntimeState;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSubscription\Enums\PeriodUnit;
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

    Console::factory()->for($consoleUser)->create();

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

it('labels the reseller list by its user username', function (): void {
    actAsLabellingConsoleUser();

    livewire(ListResellers::class)
        ->assertTableColumnExists('user.username', fn (TextColumn $column): bool => $column->getLabel() === __('vendra-console::attributes.username'));
});

it('shows store and storefront statuses as their enum badges in the store list', function (): void {
    actAsLabellingConsoleUser();

    $store = Store::factory()->active()->create();
    StorefrontDeployment::factory()->for($store)->create(['status' => StorefrontDeploymentStatus::Failed]);

    livewire(ListStores::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('status', StoreStatus::Active->getLabel(), $store)
        ->assertTableColumnExists('status', fn (TextColumn $column): bool => $column->getColor(StoreStatus::Active) === 'success', $store)
        ->assertTableColumnFormattedStateSet('storefront_status', StorefrontDeploymentStatus::Failed->getLabel(), $store)
        ->assertTableColumnExists('storefront_status', fn (TextColumn $column): bool => $column->getColor(StorefrontDeploymentStatus::Failed) === 'danger', $store);
});

it('translates plan period units in the list and the form', function (): void {
    actAsLabellingConsoleUser();

    $plan = Plan::factory()->period(PeriodUnit::Month, 3)->create();

    livewire(ListPlans::class)
        ->loadTable()
        ->assertTableColumnStateSet('period', '3 '.PeriodUnit::Month->getLabel(), $plan);

    $expectedOptions = collect(PeriodUnit::cases())
        ->mapWithKeys(fn (PeriodUnit $unit): array => [$unit->value => $unit->getLabel()])
        ->all();

    livewire(CreatePlan::class)
        ->assertFormFieldExists('period_unit', fn (Select $field): bool => $field->getOptions() === $expectedOptions);
});

it('translates every enum-derived console label', function (string $locale): void {
    $prefixedEnums = [
        'runtime_state_' => StorefrontRuntimeState::cases(),
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
})->with(['vendra-console', 'vendra-reseller', 'vendra-store', 'vendra-subscription'])->with(['fa', 'de']);
