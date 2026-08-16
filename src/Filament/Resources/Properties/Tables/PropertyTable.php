<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Misaf\VendraConsole\Filament\Resources\Properties\Actions\ReplaceDomainAction;
use Misaf\VendraProperty\Models\StorefrontDeployment;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraTenant\Models\Tenant;

final class PropertyTable
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
                    ->state(fn(Tenant $record): ?string => null === $record->reseller_id
                        ? null
                        : self::resellerNames()->get($record->reseller_id))
                    ->placeholder('—'),

                TextColumn::make('domain')
                    ->label(__('console.domain'))
                    ->icon(Heroicon::GlobeAlt)
                    ->state(fn(Tenant $record): ?string => $record->activeDomainName())
                    ->placeholder('—'),

                TextColumn::make('storefront_status')
                    ->label(__('console.storefront_status'))
                    ->badge()
                    ->state(fn(Tenant $record): ?string => self::storefrontStatuses()->get($record->id))
                    ->placeholder(__('console.storefront_not_requested')),

                TextColumn::make('admin_access')
                    ->label(__('console.admin_url'))
                    ->state(fn(Tenant $record): string => 'https://' . $record->slug . '.' . Config::string('vendra-tenant.central_host'))
                    ->description(fn(Tenant $record): ?string => $record->activeDomainName()
                        ? 'https://admin.' . $record->activeDomainName()
                        : null)
                    ->url(fn(Tenant $record): string => 'https://' . $record->slug . '.' . Config::string('vendra-tenant.central_host'))
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyMessage(__('console.url_copied'))
                    ->placeholder('—'),

                ToggleColumn::make('active')
                    ->label(__('console.active'))
                    ->onIcon(Heroicon::Bolt),

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
            ->description(__('console.tables.description.properties'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.properties'))
            ->emptyStateDescription(__('console.tables.empty_state.description.properties'))
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

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    ReplaceDomainAction::make(),

                    EditAction::make(),

                    DeleteAction::make(),

                    RestoreAction::make(),

                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
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

    /** @return Collection<int, 'failed'|'pending'|'processing'|'ready'|'requested'> */
    private static function storefrontStatuses(): Collection
    {
        return once(fn(): Collection => StorefrontDeployment::query()
            ->orderBy('id')
            ->get(['tenant_id', 'status'])
            ->mapWithKeys(fn(StorefrontDeployment $deployment): array => [
                $deployment->tenant_id => $deployment->status->value,
            ]));
    }
}
