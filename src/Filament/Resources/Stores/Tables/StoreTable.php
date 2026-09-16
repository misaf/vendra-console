<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;

final class StoreTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('name')
                    ->label(__('vendra-console::attributes.name'))
                    ->icon(Heroicon::Tag)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reseller')
                    ->label(__('vendra-console::navigation.reseller'))
                    ->state(fn (Store $record): ?string => $record->reseller_id === null
                        ? null
                        : self::resellerNames()->get($record->reseller_id))
                    ->placeholder('—'),

                TextColumn::make('domain')
                    ->label(__('vendra-console::attributes.domain'))
                    ->icon(Heroicon::GlobeAlt)
                    ->state(fn (Store $record): ?string => $record->domains->first()?->name)
                    ->placeholder('—'),

                TextColumn::make('storefront_status')
                    ->label(__('vendra-console::attributes.storefront_status'))
                    ->badge()
                    ->state(fn (Store $record): ?string => self::deployment($record)?->status->value)
                    ->formatStateUsing(fn (string $state): string => __("vendra-console::attributes.deployment_status_{$state}"))
                    ->placeholder(__('vendra-console::attributes.storefront_not_requested')),

                TextColumn::make('admin_url')
                    ->label(__('vendra-console::attributes.admin_url'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->state(fn (Store $record): string => $record->adminUrl())
                    ->url(fn (Store $record): string => $record->adminUrl())
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('vendra-console::messages.url_copied')),

                TextColumn::make('storefront_url')
                    ->label(__('vendra-console::attributes.storefront_url'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->state(fn (Store $record): ?string => self::deployment($record)?->domain)
                    ->placeholder('—')
                    ->url(fn (Store $record): ?string => self::deployment($record)?->url())
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('vendra-console::messages.url_copied')),

                TextColumn::make('status')
                    ->label(__('vendra-console::attributes.status'))
                    ->badge()
                    ->state(fn (Store $record): string => $record->status()->value)
                    ->formatStateUsing(fn (string $state): string => __("vendra-console::attributes.store_status_{$state}")),

                CreatedAtColumn::make()
                    ->sortable(),

                UpdatedAtColumn::make(),
            ])
            ->description(__('vendra-console::tables.description.stores'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.stores'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.stores'))
            ->emptyStateIcon(Heroicon::OutlinedGlobeAlt)
            ->filters(
                [
                    TernaryFilter::make('active')
                        ->label(__('vendra-console::attributes.active'))
                        ->trueLabel(__('vendra-console::attributes.active'))
                        ->falseLabel(__('vendra-console::attributes.inactive'))
                        ->queries(
                            true: fn (Builder $query): Builder => $query->where('active', true),
                            false: fn (Builder $query): Builder => $query->where('active', false),
                            blank: fn (Builder $query): Builder => $query,
                        ),

                    SelectFilter::make('reseller_id')
                        ->label(__('vendra-console::navigation.reseller'))
                        ->options(fn (): array => self::resellerNames()->all()),

                    SelectFilter::make('status')
                        ->label(__('vendra-console::attributes.operational_status'))
                        ->multiple()
                        ->options(self::statusOptions())
                        ->query(function (Builder $query, array $data): Builder {
                            $statuses = [];

                            foreach ((array) (Arr::get($data, 'values', [])) as $value) {
                                if (is_string($value) && ($status = StoreStatus::tryFrom($value)) instanceof StoreStatus) {
                                    $statuses[] = $status;
                                }
                            }

                            if ($statuses === []) {
                                return $query;
                            }

                            return $query->where(function (Builder $query) use ($statuses): void {
                                foreach ($statuses as $status) {
                                    $query->orWhere(fn (Builder $query): Builder => $query->withStatus($status));
                                }
                            });
                        }),

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
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
            ->recordUrl(fn (Store $record): string => StoreResource::getUrl('view', ['record' => $record]))
            ->defaultSort(column: 'id', direction: 'desc');
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

    private static function deployment(Store $store): ?StorefrontDeployment
    {
        $deployment = $store->storefrontDeployments->first();

        return $deployment instanceof StorefrontDeployment ? $deployment : null;
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(StoreStatus::cases())
            ->mapWithKeys(fn (StoreStatus $status): array => [
                $status->value => __("vendra-console::attributes.store_status_{$status->value}"),
            ])
            ->all();
    }
}
