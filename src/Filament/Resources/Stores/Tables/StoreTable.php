<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AssignResellerTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\OffboardStoreTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ReactivateStoreTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ReconcileStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RedeployStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ReplaceDomainTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RestartStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RestoreStoreTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RetryStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RetryStoreProvisioningTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\StartStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\StopStorefrontTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\SuspendStoreTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ViewDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ViewStorefrontLogsTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Resources\Stores\Tables\StoreTable as BaseStoreTable;
use Misaf\VendraStore\Models\Store;

final class StoreTable
{
    public static function configure(Table $table): Table
    {
        return BaseStoreTable::configure(
            $table,
            identityColumns: [
                TextColumn::make('reseller')
                    ->label(__('vendra-console::navigation.reseller'))
                    ->state(fn (Store $record): ?string => $record->reseller_id === null
                        ? null
                        : self::resellerNames()->get($record->reseller_id))
                    ->placeholder('—'),
            ],
            filtersSeveralStatuses: true,
        )
            ->pushFilters([
                SelectFilter::make('reseller_id')
                    ->label(__('vendra-console::navigation.reseller'))
                    ->options(fn (): array => self::resellerNames()->all()),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ActionGroup::make([
                        AssignResellerTableAction::make(),
                        ReplaceDomainTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        ViewDeploymentTableAction::make(),
                        ViewStorefrontLogsTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        SuspendStoreTableAction::make(),
                        ReactivateStoreTableAction::make(),
                        RetryStoreProvisioningTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        StartStorefrontTableAction::make(),
                        StopStorefrontTableAction::make(),
                        RestartStorefrontTableAction::make(),
                        ReconcileStorefrontTableAction::make(),
                        RedeployStorefrontTableAction::make(),
                        RetryStorefrontTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        OffboardStoreTableAction::make(),
                        RestoreStoreTableAction::make(),
                    ])->dropdown(false),
                ]),
            ])
            ->recordUrl(fn (Store $record): string => StoreResource::getUrl('view', ['record' => $record]));
    }

    /**
     * Reseller names keyed by id, resolved once per request to avoid a
     * per-row query when rendering the reseller column.
     *
     * @return Collection<int, string>
     */
    private static function resellerNames(): Collection
    {
        return once(fn (): Collection => Reseller::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Reseller $reseller): array => [$reseller->id => $reseller->name]));
    }
}
