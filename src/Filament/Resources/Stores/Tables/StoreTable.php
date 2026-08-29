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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AssignResellerAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ReplaceDomainAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\StoreOperatorActions;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class StoreTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                TextColumn::make('name')
                    ->label(__('console.name'))
                    ->icon(Heroicon::Tag)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reseller')
                    ->label(__('console.reseller'))
                    ->state(fn(Store $record): ?string => null === $record->reseller_id
                        ? null
                        : self::resellerNames()->get($record->reseller_id))
                    ->placeholder('—'),

                TextColumn::make('domain')
                    ->label(__('console.domain'))
                    ->icon(Heroicon::GlobeAlt)
                    ->state(fn(Store $record): ?string => $record->domains->first()?->name)
                    ->placeholder('—'),

                TextColumn::make('storefront_status')
                    ->label(__('console.storefront_status'))
                    ->badge()
                    ->state(fn(Store $record): ?string => self::deployment($record)?->status->value)
                    ->placeholder(__('console.storefront_not_requested')),

                TextColumn::make('admin_access')
                    ->label(__('console.admin_url'))
                    ->state(fn(Store $record): string => 'https://' . $record->slug . '.' . Config::string('vendra-tenant.central_host'))
                    ->description(function (Store $record): ?string {
                        $domain = $record->domains->first()?->name;

                        return null === $domain ? null : 'https://admin.' . $domain;
                    })
                    ->url(fn(Store $record): string => 'https://' . $record->slug . '.' . Config::string('vendra-tenant.central_host'))
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('console.url_copied'))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('console.status'))
                    ->badge()
                    ->state(fn(Store $record): string => $record->status()->value),

                TextColumn::make('created_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('console.created_at'))
                    ->sinceTooltip()
                    ->sortable()
                    ->when(
                        app()->isLocale('fa'),
                        fn(TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn(TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),

                TextColumn::make('updated_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('console.updated_at'))
                    ->sinceTooltip()
                    ->when(
                        app()->isLocale('fa'),
                        fn(TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn(TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),
            ])
            ->description(__('console.tables.description.stores'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.stores'))
            ->emptyStateDescription(__('console.tables.empty_state.description.stores'))
            ->emptyStateIcon(Heroicon::OutlinedGlobeAlt)
            ->filters(
                [
                    TernaryFilter::make('active')
                        ->label(__('console.active'))
                        ->trueLabel(__('console.active'))
                        ->falseLabel(__('console.inactive'))
                        ->queries(
                            true: fn(Builder $query): Builder => $query->where('active', true),
                            false: fn(Builder $query): Builder => $query->where('active', false),
                            blank: fn(Builder $query): Builder => $query,
                        ),

                    SelectFilter::make('reseller_id')
                        ->label(__('console.reseller'))
                        ->options(fn(): array => self::resellerNames()->all()),

                    SelectFilter::make('status')
                        ->label(__('console.operational_status'))
                        ->multiple()
                        ->options(self::statusOptions())
                        ->query(function (Builder $query, array $data): Builder {
                            $statuses = [];

                            foreach ((array) ($data['values'] ?? []) as $value) {
                                if (is_string($value) && ($status = StoreStatus::tryFrom($value)) instanceof StoreStatus) {
                                    $statuses[] = $status;
                                }
                            }

                            if ([] === $statuses) {
                                return $query;
                            }

                            return $query->where(function (Builder $query) use ($statuses): void {
                                foreach ($statuses as $status) {
                                    $query->orWhere(fn(Builder $query): Builder => $query->withStatus($status));
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
                        AssignResellerAction::make(),
                        ReplaceDomainAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make(StoreOperatorActions::inspection())->dropdown(false),
                    ActionGroup::make(StoreOperatorActions::storeLifecycle())->dropdown(false),
                    ActionGroup::make(StoreOperatorActions::storefrontLifecycle())->dropdown(false),
                    ActionGroup::make(StoreOperatorActions::destructive())->dropdown(false),
                ]),
            ])
            ->recordUrl(fn(Store $record): string => StoreResource::getUrl('view', ['record' => $record]))
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
        return once(fn(): Collection => Reseller::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn(Reseller $reseller): array => [$reseller->id => $reseller->name]));
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
            ->mapWithKeys(fn(StoreStatus $status): array => [
                $status->value => __("console.store_status_{$status->value}"),
            ])
            ->all();
    }
}
