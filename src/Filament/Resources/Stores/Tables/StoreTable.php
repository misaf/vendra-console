<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
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
use Misaf\VendraStore\Actions\ReactivateStoreAction;
use Misaf\VendraStore\Actions\SuspendStoreAction;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveToggleColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\NameColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Filters\IsActiveFilter;
use Misaf\VendraTenant\Enums\TenantProvisioningStatus;
use Throwable;

final class StoreTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                NameColumn::make()
                    ->icon(null)
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
                    ->state(fn (Store $record): ?string => $record->domains->first()?->name)
                    ->placeholder('—'),

                TextColumn::make('storefront_status')
                    ->label(__('vendra-console::attributes.storefront_status'))
                    ->badge()
                    ->state(fn (Store $record): ?StorefrontDeploymentStatus => self::deployment($record)?->status)
                    ->placeholder(__('vendra-console::attributes.storefront_not_requested')),

                TextColumn::make('admin_url')
                    ->label(__('vendra-console::attributes.admin_url'))
                    ->state(fn (Store $record): string => $record->adminUrl())
                    ->url(fn (Store $record): string => $record->adminUrl())
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('vendra-console::messages.url_copied')),

                TextColumn::make('storefront_url')
                    ->label(__('vendra-console::attributes.storefront_url'))
                    ->state(fn (Store $record): ?string => self::deployment($record)?->domain)
                    ->placeholder('—')
                    ->url(fn (Store $record): ?string => self::deployment($record)?->url())
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('vendra-console::messages.url_copied')),

                IsActiveToggleColumn::make()
                    ->disabled(fn (Store $record): bool => $record->trashed()
                        || (! $record->active && $record->provisioning_status !== TenantProvisioningStatus::Ready))
                    ->updateStateUsing(fn (Store $record, bool $state): bool => self::setActive($record, $state)),

                TextColumn::make('status')
                    ->label(__('vendra-console::attributes.operational_status'))
                    ->badge()
                    ->state(fn (Store $record): StoreStatus => $record->status()),

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
                    IsActiveFilter::make(),

                    SelectFilter::make('reseller_id')
                        ->label(__('vendra-console::navigation.reseller'))
                        ->options(fn (): array => self::resellerNames()->all()),

                    SelectFilter::make('status')
                        ->label(__('vendra-console::attributes.operational_status'))
                        ->multiple()
                        ->options(StoreStatus::class)
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
        return once(fn (): Collection => collect(Reseller::displayNames(Reseller::query())));
    }

    private static function deployment(Store $store): ?StorefrontDeployment
    {
        $deployment = $store->storefrontDeployments->first();

        return $deployment instanceof StorefrontDeployment ? $deployment : null;
    }

    private static function setActive(Store $store, bool $active): bool
    {
        try {
            $store = $active
                ? resolve(ReactivateStoreAction::class)->execute($store)
                : resolve(SuspendStoreAction::class)->execute($store);

            Notification::make()
                ->success()
                ->title(__($active ? 'vendra-console::messages.store_reactivated' : 'vendra-console::messages.store_suspended'))
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('vendra-console::messages.operational_action_failed'))
                ->body($exception->getMessage())
                ->send();
        }

        return (bool) $store->fresh()?->active;
    }
}
