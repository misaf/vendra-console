<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use LogicException;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraUser\Exceptions\LastAdministratorException;

trait InteractsWithAdministratorRecord
{
    protected static function administratorStore(RelationManager $livewire): Store
    {
        $store = $livewire->getOwnerRecord();

        throw_unless($store instanceof Store, LogicException::class, 'Administrator membership requires a Store parent record.');

        return $store;
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
