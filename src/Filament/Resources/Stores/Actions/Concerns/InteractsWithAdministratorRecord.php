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
     * Memoized per store and user for the request: the column and two action
     * visibility checks all ask, and each answer switches tenancy.
     */
    protected static function isAdministrator(Store $store, User $user): bool
    {
        return once(fn (): bool => $store->execute(fn (): bool => $user->hasRole(Config::string('vendra-permission.admin_role'))));
    }

    /**
     * Runs an operation that may refuse to remove the store's last administrator,
     * turning that refusal into a notification instead of an error page.
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
     * Every membership change ends here, so the memoized role answers are
     * dropped before the table renders again.
     */
    protected static function notifySuccess(string $title): void
    {
        Once::flush();

        Notification::make()->success()->title($title)->send();
    }
}
