<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use LogicException;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraUser\Exceptions\LastAdministratorException;
use Misaf\VendraUser\Models\User;

trait InteractsWithAdministratorRecord
{
    protected static function administratorStore(RelationManager $livewire): Store
    {
        $store = $livewire->getOwnerRecord();

        throw_unless($store instanceof Store, LogicException::class, 'Administrator membership requires a Store parent record.');

        return $store;
    }

    /**
     * Determine if the user administers the store.
     *
     * The table loads roles for its whole page up front; a record loaded on its
     * own (an action's re-read after a change) loads them here inside the
     * store's context, so it sees the new roles.
     */
    protected static function isAdministrator(Store $store, User $user): bool
    {
        if (! $user->relationLoaded('roles')) {
            $store->execute(fn (): User => $user->load('roles'));
        }

        return $user->hasRole(Config::string('vendra-permission.admin_role'));
    }

    /**
     * Load the roles of a page of users in one query inside the store's context.
     *
     * @param  Collection<array-key, User>  $users
     * @return Collection<array-key, User>
     */
    protected static function loadAdministratorRoles(Store $store, Collection $users): Collection
    {
        $store->execute(fn (): Collection => $users->load('roles'));

        return $users;
    }

    /**
     * Run an operation, reporting a last-administrator refusal as a notification.
     *
     * @param  callable(): mixed  $operation
     */
    protected static function guardLastAdministrator(callable $operation, string $successTitle): void
    {
        try {
            $operation();
        } catch (LastAdministratorException $exception) {
            Notification::make()
                ->danger()
                ->title(__('vendra-console::messages.last_administrator_required'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        self::notifySuccess($successTitle);
    }

    protected static function notifySuccess(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
