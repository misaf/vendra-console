<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Once;
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
     * Determine if the user administers the store, memoized per request.
     */
    protected static function isAdministrator(Store $store, User $user): bool
    {
        return once(fn (): bool => $store->execute(fn (): bool => $user->hasRole(Config::string('vendra-permission.admin_role'))));
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

    /**
     * Notify success and clear the memoized role answers.
     */
    protected static function notifySuccess(string $title): void
    {
        Once::flush();

        Notification::make()->success()->title($title)->send();
    }
}
