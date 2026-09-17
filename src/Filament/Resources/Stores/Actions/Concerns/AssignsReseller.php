<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Actions\AssignStoreResellerAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;

/**
 * Hand a store to a reseller, to a different reseller, or back to the platform.
 *
 * A deliberate operation rather than a field on the edit form: reassignment
 * consumes a slot in the receiving reseller's plan, and a plain `reseller_id`
 * select would write the column straight past that check.
 */
trait AssignsReseller
{
    public static function getDefaultName(): string
    {
        return 'assignReseller';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.assign_reseller'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->modalDescription(__('vendra-console::messages.assign_reseller_description'))
            ->fillForm(fn (Store $record): array => ['reseller_id' => $record->reseller_id])
            ->schema([
                Select::make('reseller_id')
                    ->label(__('vendra-console::navigation.reseller'))
                    ->options(fn (): array => Reseller::displayNames(Reseller::query()->active()))
                    ->placeholder(__('vendra-console::attributes.platform_owned_store'))
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->action(function (Store $record, array $data): void {
                $resellerId = Arr::get($data, 'reseller_id', null);
                $reseller = is_numeric($resellerId)
                    ? Reseller::query()->find((int) $resellerId)
                    : null;

                try {
                    resolve(AssignStoreResellerAction::class)->execute($record, $reseller);
                } catch (SubscriptionLimitException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('vendra-console::messages.assign_reseller_failed'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('vendra-console::messages.reseller_assigned'))
                    ->send();
            });
    }
}
