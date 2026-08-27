<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Database\Seeders\ConsoleUserSeeder;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\CreatePlan;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\EditPlan;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\ListPlans;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\CreateReseller;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\CreateStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\ListStorefrontImages;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\CreateStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\EditStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\AdministratorsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\DomainsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource as ConsoleStoreResource;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Models\ResellerUser;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StoreDomain;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Models\StorefrontImage;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraUser\Models\User;

use function Pest\Laravel\actingAs;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('vendra-container.endpoint', 'http://provisioner:8080');
    fakeDockerEngine();
});

function consoleAdmin(): ConsoleUser
{
    return ConsoleUser::factory()->create();
}

function actAsConsoleAdmin(): ConsoleUser
{
    $admin = consoleAdmin();
    actingAs($admin, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));

    return $admin;
}

function consoleStorefrontFormData(): array
{
    return [
        'storefront_image_id'             => StorefrontImage::factory()->create()->id,
        'storefront_slug'                 => 'console-flowers',
        'storefront_theme'                => 'default',
        'storefront_name_en'              => 'Console Flowers',
        'storefront_name_fa'              => 'گل‌فروشی کنسول',
        'storefront_business_type'        => 'Florist',
        'storefront_price_currency'       => 'IRR',
        'storefront_locality'             => 'Tehran',
        'storefront_country'              => 'IR',
        'storefront_mobile_phone'         => '09120000000',
        'storefront_office_phone'         => '02100000000',
        'storefront_contact_email'        => 'contact@console-flowers.test',
        'storefront_hours_open'           => '08:00',
        'storefront_hours_close'          => '21:00',
        'storefront_map_query'            => '35.7,51.4',
        'storefront_whatsapp_phone'       => '+989120000000',
        'storefront_telegram_username'    => 'consoleflowers',
        'storefront_instagram_username'   => 'consoleflowers',
    ];
}

it('uses the Vendra logo in light and dark modes', function (): void {
    $panel = Filament::getPanel('console');

    expect($panel->getBrandName())->toBe('Vendra Console')
        ->and($panel->getBrandLogo())->toBe(asset('images/vendra-logo.svg'))
        ->and($panel->getDarkModeBrandLogo())->toBe(asset('images/vendra-logo-dark.svg'))
        ->and($panel->getBrandLogoHeight())->toBe('2rem');
});

it('lets console operators define storefront images and their themes', function (): void {
    actAsConsoleAdmin();

    livewire(CreateStorefrontImage::class)
        ->fillForm([
            'name'   => 'Florist 2026.08',
            'image'  => 'ghcr.io/misaf/storefront@sha256:abc123',
            'themes' => ['default', 'minimal'],
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas('storefront_images', [
        'name'   => 'Florist 2026.08',
        'image'  => 'ghcr.io/misaf/storefront@sha256:abc123',
        'active' => true,
    ]);
});

it('globally searches console resources', function (): void {
    actAsConsoleAdmin();

    $plan = Plan::factory()->create(['name' => 'Enterprise Search Plan']);
    $reseller = Reseller::factory()->create([
        'name'  => 'Search Partner',
        'email' => 'partner-search@example.com',
    ]);
    $store = Store::factory()->create(['name' => 'Search Store']);
    StoreDomain::factory()->for($store)->create([
        'name'   => 'global-search-store.test',
        'active' => true,
    ]);

    $planResult = PlanResource::getGlobalSearchResults('enterprise')->sole();
    $resellerResult = ResellerResource::getGlobalSearchResults('partner-search@example.com')->sole();
    $storeResult = ConsoleStoreResource::getGlobalSearchResults('global-search-store.test')->sole();
    $storeAction = $storeResult->actions[0];

    expect($planResult->title)->toBe($plan->name)
        ->and($planResult->url)->toBe(PlanResource::getUrl('edit', ['record' => $plan]))
        ->and($resellerResult->title)->toBe($reseller->name)
        ->and($resellerResult->url)->toBe(ResellerResource::getUrl('edit', ['record' => $reseller]))
        ->and($storeResult->title)->toBe($store->name)
        ->and($storeResult->url)->toBe(ConsoleStoreResource::getUrl('edit', ['record' => $store]))
        ->and($storeResult->details)->toBe([
            __('console.domain') => 'global-search-store.test',
        ])
        ->and($storeAction->getLabel())->toBe(__('console.admin_url'))
        ->and($storeAction->getUrl())->toBe(
            'https://' . $store->slug . '.' . Config::string('vendra-tenant.central_host'),
        )
        ->and($storeAction->shouldOpenUrlInNewTab())->toBeTrue();
});

it('isolates console operators from application users', function (): void {
    $tenant = createTestTenant();
    $admin = ConsoleUser::factory()->create();
    $regular = User::factory()->forTenant($tenant)->create();

    $panel = Filament::getPanel('console');

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($regular->canAccessPanel($panel))->toBeFalse()
        ->and($panel->getAuthGuard())->toBe('console')
        ->and($panel->getAuthPasswordBroker())->toBe('console_users')
        ->and(config('auth.guards.console.provider'))->toBe('console_users')
        ->and(config('auth.providers.console_users.model'))->toBe(ConsoleUser::class)
        ->and(Filament::getPanel('reseller')->getAuthGuard())->toBe('reseller')
        ->and(Filament::getPanel('admin')->getAuthGuard())->toBe('web');

    actingAs($admin, 'console');

    expect(auth('console')->id())->toBe($admin->getKey())
        ->and(auth('web')->check())->toBeFalse();
});

it('redirects an application user away from the console panel', function (): void {
    actingAs(User::factory()->forTenant(createTestTenant())->create());

    $this->get('https://console.vendra.test')
        ->assertRedirect('https://console.vendra.test/login');
});

it('allows a verified console operator into the console panel', function (): void {
    actingAs(ConsoleUser::factory()->create(), 'console');

    $this->get('https://console.vendra.test')->assertOk();
});

it('seeds the initial console operator only from explicit credentials', function (): void {
    Config::set('console.operator', [
        'username' => 'console_owner',
        'email'    => 'OWNER@EXAMPLE.TEST',
        'password' => 'a-secure-console-password',
    ]);

    app(ConsoleUserSeeder::class)->run();

    $operator = ConsoleUser::query()->sole();

    expect($operator->username)->toBe('console_owner')
        ->and($operator->email)->toBe('owner@example.test')
        ->and($operator->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-secure-console-password', $operator->password))->toBeTrue();
});

it('does not seed a console operator when explicit credentials are absent', function (): void {
    Config::set('console.operator', [
        'username' => '',
        'email'    => '',
        'password' => '',
    ]);

    app(ConsoleUserSeeder::class)->run();

    expect(ConsoleUser::query()->count())->toBe(0);
});

it('lets a console admin create a plan', function (): void {
    actAsConsoleAdmin();

    livewire(CreatePlan::class)
        ->fillForm([
            'name'          => 'Pro',
            'max_units'     => 3,
            'period_unit'   => 'month',
            'period_count'  => 1,
            'active'        => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('plans', [
        'name'          => 'Pro',
        'max_units'     => 3,
        'period_unit'   => 'month',
    ]);
});

it('requires a currency for a paid plan', function (): void {
    actAsConsoleAdmin();

    livewire(CreatePlan::class)
        ->fillForm([
            'name'           => 'Paid',
            'max_units'      => 3,
            'period_unit'    => 'month',
            'period_count'   => 1,
            'price'          => 1500,
            'currency_code'  => null,
            'active'         => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['currency_code' => 'required']);
});

it('honors a disabled state when creating a reseller', function (): void {
    actAsConsoleAdmin();

    livewire(CreateReseller::class)
        ->fillForm([
            'plan_id'               => Plan::factory()->create()->getKey(),
            'username'              => 'paused_owner',
            'email'                 => 'owner@gmail.com',
            'password'              => 'Secure123',
            'password_confirmation' => 'Secure123',
            'active'                => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $reseller = Reseller::query()->where('name', 'paused_owner')->sole();

    expect($reseller->active)->toBeFalse()
        ->and($reseller->ownerUser()->sole())->toBeInstanceOf(ResellerUser::class)
        ->and(Hash::check('Secure123', $reseller->ownerUser()->sole()->password))->toBeTrue();
});

it('prevents deleting a plan used by subscriptions from list and edit pages', function (): void {
    actAsConsoleAdmin();

    $plan = Plan::factory()->create();
    Subscription::factory()->for($plan)->create();

    livewire(ListPlans::class)
        ->assertActionHidden(TestAction::make('delete')->table($plan));

    livewire(EditPlan::class, ['record' => $plan->getKey()])
        ->assertActionHidden(DeleteAction::class);
});

it('allows deleting an unused plan from list and edit pages', function (): void {
    actAsConsoleAdmin();

    $plan = Plan::factory()->create();

    livewire(ListPlans::class)
        ->assertActionVisible(TestAction::make('delete')->table($plan));

    livewire(EditPlan::class, ['record' => $plan->getKey()])
        ->assertActionVisible(DeleteAction::class);
});

it('creates a store for a reseller within its plan limit', function (): void {
    actAsConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->maxUnits(2))->create();

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id' => $reseller->getKey(),
            'domain'      => 'acme.test',
            'email'       => 'admin@gmail.com',
            'active'      => true,
            ...consoleStorefrontFormData(),
            'storefront_slug' => 'acme',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('stores', [
        'name'        => 'Acme',
        'reseller_id' => $reseller->getKey(),
    ]);
    assertDatabaseHas('users', [
        'username' => 'admin',
        'email'    => 'admin@gmail.com',
    ]);
    expect(StorefrontDeployment::query()->count())->toBe(1);
});

it('uses a wizard when creating a store and florist storefront', function (): void {
    actAsConsoleAdmin();

    $component = livewire(CreateStore::class);

    expect($component->instance()->hasSkippableSteps())->toBeTrue();

    $component
        ->assertWizardCurrentStep(1)
        ->assertWizardStepExists(2)
        ->assertWizardStepExists(3)
        ->assertWizardStepExists(4)
        ->assertFormFieldExists('create_storefront')
        ->assertSee(__('console.storefront_map_query'))
        // The billing reseller is optional; leaving it empty makes a
        // platform-owned store, so it must not appear among the errors.
        ->assertFormFieldExists('reseller_id')
        ->call('create')
        ->assertHasFormErrors([
            'domain'                    => 'required',
            'email'                     => 'required',
            'storefront_slug'           => 'required',
            'storefront_name_en'        => 'required',
            'storefront_name_fa'        => 'required',
            'storefront_mobile_phone'   => 'required',
            'storefront_office_phone'   => 'required',
            'storefront_contact_email'  => 'required',
            'storefront_hours_open'     => 'required',
            'storefront_hours_close'    => 'required',
            'storefront_locality'       => 'required',
            'storefront_map_query'      => 'required',
        ])
        ->assertHasNoFormErrors(['reseller_id']);
});

it('lets a console admin create a store without a managed storefront', function (): void {
    actAsConsoleAdmin();

    livewire(CreateStore::class)
        ->fillForm([
            'domain'            => 'local-source.test',
            'email'             => 'local-source@gmail.com',
            'active'            => true,
            'create_storefront' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('store_domains', ['name' => 'local-source.test']);
    assertDatabaseMissing('storefront_deployments', ['domain' => 'local-source.test']);
});

it('suggests storefront identity from the store domain', function (): void {
    actAsConsoleAdmin();

    livewire(CreateStore::class)
        ->set('data.domain', 'Rose-Garden.Example')
        ->assertHasNoFormErrors(['domain'])
        ->assertFormSet([
            'storefront_slug'    => 'rose-garden',
            'storefront_name_en' => 'Rose Garden',
        ]);
});

it('requests a storefront when a console admin creates a store', function (): void {
    actAsConsoleAdmin();
    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->maxUnits(2))->create();

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id' => $reseller->getKey(),
            'domain'      => 'console-flowers.test',
            'email'       => 'console.flowers@gmail.com',
            'active'      => true,
            ...consoleStorefrontFormData(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('storefront_deployments', [
        'slug'   => 'console-flowers',
        'domain' => 'console-flowers.test',
        'theme'  => 'default',
        'status' => 'ready',
    ]);
});

it('blocks store creation once the reseller reaches its plan limit', function (): void {
    actAsConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->maxUnits(1))->create();
    Store::factory()->create(['reseller_id' => $reseller->getKey()]);

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id'    => $reseller->getKey(),
            'domain'         => 'second.test',
            'email'          => 'admin@second.test',
            'active'         => true,
        ])
        ->call('create');

    assertDatabaseMissing('store_domains', ['name' => 'second.test']);
    expect($reseller->stores()->count())->toBe(1);
});

it('validates store domains during creation', function (): void {
    actAsConsoleAdmin();

    $reseller = Reseller::factory()->create();
    Subscription::factory()->forSubscriber($reseller)->for(Plan::factory()->maxUnits(3))->create();
    $existingStore = Store::factory()->create();
    StoreDomain::factory()->for($existingStore)->create(['name' => 'taken.test', 'active' => true]);

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id'    => $reseller->getKey(),
            'domain'         => 'not a domain',
            'email'          => 'invalid@example.test',
            'active'         => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['domain' => 'regex']);

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id'    => $reseller->getKey(),
            'domain'         => 'taken.test',
            'email'          => 'duplicate@example.test',
            'active'         => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['domain' => 'unique']);
});

it('lets a console admin replace a domain and shows the old one in trashed history', function (): void {
    actAsConsoleAdmin();

    $store = Store::factory()->create(['active' => true]);
    $original = StoreDomain::factory()->for($store)->create(['name' => 'old.test', 'active' => true]);

    livewire(ListStores::class)
        ->callAction(TestAction::make('replaceDomain')->table($store), ['domain' => 'new.test'])
        ->assertHasNoErrors();

    $current = $store->execute(fn() => $store->storeDomains()->where('active', true)->value('name'));
    expect($current)->toBe('new.test');

    livewire(DomainsRelationManager::class, [
        'ownerRecord' => $store,
        'pageClass'   => EditStore::class,
    ])
        ->call('loadTable')
        ->filterTable('trashed', ['value' => '0'])
        ->assertCanSeeTableRecords([$original]);
});

it('adds a store administrator through the tenant membership action', function (): void {
    actAsConsoleAdmin();

    $store = Store::factory()->create();
    $roleClass = app(PermissionRegistrar::class)->getRoleClass();
    $store->execute(fn(): mixed => $roleClass::query()->firstOrCreate([
        'name'       => Config::string('vendra-permission.admin_role'),
        'guard_name' => 'web',
    ]));

    livewire(AdministratorsRelationManager::class, [
        'ownerRecord' => $store,
        'pageClass'   => EditStore::class,
    ])
        ->callAction(TestAction::make('addAdministrator')->table(), [
            'username'              => 'second_admin',
            'email'                 => 'second-admin@example.com',
            'password'              => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ])
        ->assertHasNoActionErrors();

    $administrator = $store->execute(fn(): User => User::query()->where('email', 'second-admin@example.com')->sole());

    expect($administrator->tenants()->whereKey($store->getKey())->exists())->toBeTrue()
        ->and($store->execute(fn(): bool => $administrator->hasRole(Config::string('vendra-permission.admin_role'))))->toBeTrue();
});

it('lets a console admin offboard then restore a store', function (): void {
    actAsConsoleAdmin();

    $store = Store::factory()->create(['active' => true]);

    livewire(ListStores::class)
        ->callAction(TestAction::make('offboardStore')->table($store), [
            'reason' => 'Customer requested account closure.',
        ])
        ->assertHasNoErrors();

    expect($store->fresh()?->trashed())->toBeTrue();

    livewire(ListStores::class)
        ->loadTable()
        ->filterTable('trashed', ['value' => 'trashed'])
        ->callAction(TestAction::make('restoreOffboardedStore')->table($store))
        ->assertHasNoErrors();

    expect($store->fresh()?->trashed())->toBeFalse();
});

it('does not expose permanent deletion for an offboarded store', function (): void {
    actAsConsoleAdmin();

    $store = Store::factory()->trashed()->create();

    livewire(ListStores::class)
        ->loadTable()
        ->filterTable('trashed', ['value' => 'trashed'])
        ->assertActionDoesNotExist(TestAction::make('forceDelete')->table($store));

    expect($store->fresh()?->trashed())->toBeTrue();
});

it('filters stores by active', function (): void {
    actAsConsoleAdmin();

    $active = Store::factory()->create(['active' => true]);
    $inactive = Store::factory()->create(['active' => false]);

    livewire(ListStores::class)
        ->loadTable()
        ->filterTable('active', ['value' => true])
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('filters stores by reseller', function (): void {
    actAsConsoleAdmin();

    $reseller = Reseller::factory()->create(['active' => true]);
    $owned = Store::factory()->create(['reseller_id' => $reseller->getKey(), 'active' => true]);
    $unowned = Store::factory()->create(['active' => true]);

    livewire(ListStores::class)
        ->loadTable()
        ->filterTable('reseller_id', $reseller->getKey())
        ->assertCanSeeTableRecords([$owned])
        ->assertCanNotSeeTableRecords([$unowned]);
});

it('filters resellers by active', function (): void {
    actAsConsoleAdmin();

    $active = Reseller::factory()->create(['active' => true]);
    $inactive = Reseller::factory()->create(['active' => false]);

    livewire(ListResellers::class)
        ->loadTable()
        ->filterTable('active', ['value' => true])
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('filters plans by period unit', function (): void {
    actAsConsoleAdmin();

    $monthly = Plan::factory()->create(['period_unit' => PeriodUnit::Month]);
    $yearly = Plan::factory()->create(['period_unit' => PeriodUnit::Year]);

    livewire(ListPlans::class)
        ->loadTable()
        ->filterTable('period_unit', PeriodUnit::Month->value)
        ->assertCanSeeTableRecords([$monthly])
        ->assertCanNotSeeTableRecords([$yearly]);
});

it('uses the package table presentation conventions in the console', function (
    string $page,
    string $resource,
    Heroicon $emptyStateIcon,
): void {
    actAsConsoleAdmin();

    $component = livewire($page)
        ->assertTableColumnExists('row')
        ->assertTableColumnExists('created_at')
        ->assertTableColumnExists('updated_at');
    $table = $component->instance()->getTable();

    expect($table->getDescription())->toBe(__("console.tables.description.{$resource}"))
        ->and($table->getEmptyStateHeading())->toBe(__("console.tables.empty_state.heading.{$resource}"))
        ->and($table->getEmptyStateDescription())->toBe(__("console.tables.empty_state.description.{$resource}"))
        ->and($table->getEmptyStateIcon())->toBe($emptyStateIcon)
        ->and($table->getFiltersLayout())->toBe(FiltersLayout::AboveContentCollapsible);
})->with([
    'plans' => [
        ListPlans::class,
        'plans',
        Heroicon::OutlinedRectangleStack,
    ],
    'resellers' => [
        ListResellers::class,
        'resellers',
        Heroicon::OutlinedBuildingOffice2,
    ],
    'stores' => [
        ListStores::class,
        'stores',
        Heroicon::OutlinedGlobeAlt,
    ],
    'storefront images' => [
        ListStorefrontImages::class,
        'storefront_images',
        Heroicon::OutlinedCube,
    ],
]);

it('creates a store the platform owns directly, with no reseller', function (): void {
    actAsConsoleAdmin();

    livewire(CreateStore::class)
        ->fillForm([
            'reseller_id' => null,
            'domain'      => 'direct.test',
            'email'       => 'owner@gmail.com',
            'active'      => true,
            ...consoleStorefrontFormData(),
            'storefront_slug' => 'direct',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('stores', [
        'name'        => 'Direct',
        'reseller_id' => null,
    ]);

    $store = Store::query()->where('name', 'Direct')->firstOrFail();

    expect($store->reseller_id)->toBeNull()
        ->and($store->domains()->where('active', true)->value('name'))->toBe('direct.test');
});

it('manages resellers and the stores that belong to them', function (): void {
    actAsConsoleAdmin();

    $reseller = Reseller::factory()->create();
    $other = Reseller::factory()->create();

    $owned = Store::factory()->active()->create(['reseller_id' => $reseller->getKey()]);
    $foreign = Store::factory()->active()->create(['reseller_id' => $other->getKey()]);
    $direct = Store::factory()->active()->create();

    // The console is the cross-tenant surface: it sees every store, whoever owns it.
    livewire(ListStores::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$owned, $foreign, $direct]);

    livewire(ListResellers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$reseller, $other]);

    expect($reseller->stores()->pluck('id')->all())->toBe([$owned->getKey()])
        ->and($other->stores()->pluck('id')->all())->toBe([$foreign->getKey()])
        ->and($direct->reseller_id)->toBeNull();
});
