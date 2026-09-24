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
use Misaf\VendraUser\Actions\DemoteTenantAdministratorAction;
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

it('demotes a store administrator off the list while keeping them in the store', function (): void {
    $store = consoleStoreWithAdministratorRole();
    consoleStoreAdministrator($store, 'first_admin');
    $second = consoleStoreAdministrator($store, 'second_admin');

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->loadTable()
        ->callAction(TestAction::make('demoteAdministrator')->table($second))
        ->assertNotified(__('vendra-console::messages.administrator_demoted'))
        ->assertCanNotSeeTableRecords([$second]);

    expect(consoleStoreUserIsAdministrator($store, $second))->toBeFalse()
        ->and($second->tenants()->whereKey($store->getKey())->exists())->toBeTrue();
});

it('lists only users holding the admin role of this store', function (): void {
    $store = consoleStoreWithAdministratorRole();
    $administrator = consoleStoreAdministrator($store, 'store_admin');
    $member = consoleStoreAdministrator($store, 'store_member');
    consoleStoreAdministrator($store, 'other_admin_keeps_last');
    resolve(DemoteTenantAdministratorAction::class)->execute($store, $member);

    $otherStore = consoleStoreWithAdministratorRole();
    $otherAdministrator = consoleStoreAdministrator($otherStore, 'other_store_admin');
    $otherAdministrator->tenants()->attach($store->getKey());

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->loadTable()
        ->assertCanSeeTableRecords([$administrator])
        ->assertCanNotSeeTableRecords([$member, $otherAdministrator]);
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

it('lets an administrator take the email of a disabled account', function (): void {
    $store = consoleStoreWithAdministratorRole();
    $disabled = consoleStoreAdministrator($store, 'disabled_admin');
    $administrator = consoleStoreAdministrator($store, 'active_admin');
    $store->execute(fn (): ?bool => consoleStoreUser($store, $disabled)->delete());

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->assertActionHidden(TestAction::make('removeAdministrator')->table(consoleStoreUser($store, $disabled)))
        ->callAction(TestAction::make('changeAdministratorEmail')->table($administrator), ['email' => 'disabled_admin@example.com'])
        ->assertHasNoFormErrors()
        ->assertNotified(__('vendra-console::messages.administrator_email_updated'));

    expect(consoleStoreUser($store, $administrator)->email)->toBe('disabled_admin@example.com');
});

it('filters store administrators by whether their email is verified', function (string $operator, bool $expectVerified): void {
    $store = consoleStoreWithAdministratorRole();
    $verified = consoleStoreAdministrator($store, 'verified_admin');
    $unverified = consoleStoreAdministrator($store, 'unverified_admin');
    $store->execute(fn (): bool => consoleStoreUser($store, $unverified)->forceFill(['email_verified_at' => null])->save());

    [$shown, $hidden] = $expectVerified ? [$verified, $unverified] : [$unverified, $verified];

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->loadTable()
        ->filterTable('queryBuilder', ['rules' => [
            'rule' => ['type' => 'email_verified_at', 'data' => ['operator' => $operator, 'settings' => []]],
        ]])
        ->assertCanSeeTableRecords([$shown])
        ->assertCanNotSeeTableRecords([$hidden]);
})->with([
    'verified' => ['isFilled', true],
    'unverified' => ['isFilled.inverse', false],
]);

it('lists disabled administrators as inactive by default', function (): void {
    $store = consoleStoreWithAdministratorRole();
    $disabled = consoleStoreAdministrator($store, 'disabled_admin');
    $administrator = consoleStoreAdministrator($store, 'active_admin');
    $store->execute(fn (): ?bool => consoleStoreUser($store, $disabled)->delete());

    livewire(AdministratorsRelationManager::class, ['ownerRecord' => $store, 'pageClass' => EditStore::class])
        ->loadTable()
        ->assertCanSeeTableRecords([consoleStoreUser($store, $disabled), $administrator])
        ->assertTableColumnStateSet('active', false, consoleStoreUser($store, $disabled))
        ->assertTableColumnStateSet('active', true, $administrator);
});
