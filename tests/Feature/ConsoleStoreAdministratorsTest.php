<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\EditStore;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\AdministratorsRelationManager;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSupport\Tenancy\Events\TenantProvisioned;
use Misaf\VendraUser\Actions\AddTenantAdministratorAction;
use Misaf\VendraUser\Models\User;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Event::fake([TenantProvisioned::class]);
    Artisan::shouldReceive('call')->andReturn(0);
    Config::set('container.drivers.docker.host', 'http://provisioner:8080');
    fakeDockerEngine();

    $consoleUser = User::factory()->create(['tenant_id' => null]);
    Console::factory()->for($consoleUser)->create();
    actingAs($consoleUser, 'console');
    Filament::setCurrentPanel(Filament::getPanel('console'));
});

function consoleStoreWithAdministratorRole(): Store
{
    $store = Store::factory()->create();
    $roleClass = resolve(PermissionRegistrar::class)->getRoleClass();
    $store->execute(fn (): mixed => $roleClass::query()->firstOrCreate([
        'name' => Config::string('vendra-permission.admin_role'),
        'guard_name' => 'web',
    ]));

    return $store;
}

function consoleStoreAdministrator(Store $store, string $username): User
{
    return resolve(AddTenantAdministratorAction::class)->execute($store, $username, "{$username}@example.com", 'SecurePassword123');
}

function consoleStoreUser(Store $store, User $user): User
{
    return $store->execute(fn (): User => User::query()->withTrashed()->findOrFail($user->getKey()));
}

function consoleStoreUserIsAdministrator(Store $store, User $user): bool
{
    return $store->execute(fn (): bool => consoleStoreUser($store, $user)->hasRole(Config::string('vendra-permission.admin_role')));
}

it('demotes and promotes a store administrator through row actions', function (): void {
    $store = consoleStoreWithAdministratorRole();
    consoleStoreAdministrator($store, 'first_admin');
    $second = consoleStoreAdministrator($store, 'second_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('demoteAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.administrator_demoted'));

    expect(consoleStoreUserIsAdministrator($store, $second))->toBeFalse();

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('promoteAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.administrator_promoted'));

    expect(consoleStoreUserIsAdministrator($store, $second))->toBeTrue();
});

it('keeps the last store administrator when demoting or removing them', function (): void {
    $store = consoleStoreWithAdministratorRole();
    $administrator = consoleStoreAdministrator($store, 'only_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('demoteAdministrator')->table($administrator))
        ->assertNotified(__('vendra-console::messages.last_administrator_required'))
        ->callAction(TestAction::make('removeAdministrator')->table($administrator))
        ->assertNotified(__('vendra-console::messages.last_administrator_required'));

    expect(consoleStoreUserIsAdministrator($store, $administrator))->toBeTrue()
        ->and($administrator->tenants()->whereKey($store->getKey())->exists())->toBeTrue();
});

it('disables and re-enables a store administrator account', function (): void {
    $store = consoleStoreWithAdministratorRole();
    consoleStoreAdministrator($store, 'first_admin');
    $second = consoleStoreAdministrator($store, 'second_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('disableAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.account_disabled'));

    expect(consoleStoreUser($store, $second)->trashed())->toBeTrue();

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('enableAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.account_enabled'));

    expect(consoleStoreUser($store, $second)->trashed())->toBeFalse();
});

it('removes a store administrator from the store', function (): void {
    $store = consoleStoreWithAdministratorRole();
    consoleStoreAdministrator($store, 'first_admin');
    $second = consoleStoreAdministrator($store, 'second_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('removeAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.administrator_removed'));

    expect($second->tenants()->whereKey($store->getKey())->exists())->toBeFalse();
});

it('changes a store administrator password', function (): void {
    $store = consoleStoreWithAdministratorRole();
    $administrator = consoleStoreAdministrator($store, 'only_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->callAction(TestAction::make('changeAdministratorPassword')->table($administrator), [
            'password' => 'NewSecurePassword456',
            'password_confirmation' => 'NewSecurePassword456',
        ])
        ->assertHasNoActionErrors();

    expect(Hash::check('NewSecurePassword456', consoleStoreUser($store, $administrator)->password))->toBeTrue();
});
