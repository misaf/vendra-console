<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Actions\AssignStoreOwnerAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;

/**
 * Hand a store to a reseller, to a different reseller, or back to the platform.
 *
 * A deliberate operation rather than a field on the edit form: reassignment
 * consumes a slot in the receiving reseller's plan, and a plain `reseller_id`
 * select would write the column straight past that check.
 */
final class AssignResellerAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'assignReseller';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.assign_reseller'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->modalDescription(__('console.assign_reseller_description'))
            ->fillForm(fn(Store $record): array => ['reseller_id' => $record->reseller_id])
            ->schema([
                Select::make('reseller_id')
                    ->label(__('console.reseller'))
                    ->options(fn(): array => Reseller::query()->active()->pluck('name', 'id')->all())
                    ->placeholder(__('console.platform_owned_store'))
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->action(function (Store $record, array $data): void {
                $resellerId = $data['reseller_id'] ?? null;
                $reseller = is_numeric($resellerId)
                    ? Reseller::query()->find((int) $resellerId)
                    : null;

                try {
                    app(AssignStoreOwnerAction::class)->execute($record, $reseller);
                } catch (SubscriptionLimitException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('console.assign_reseller_failed'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('console.reseller_assigned'))
                    ->send();
            });
    }
}
