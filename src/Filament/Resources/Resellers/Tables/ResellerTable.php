<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ResellerOperatorActions;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerBulkAction;
use Misaf\VendraReseller\Models\Reseller;

final class ResellerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->withCount('stores'))
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                TextColumn::make('name')
                    ->label(__('console.username'))
                    ->icon(Heroicon::Tag)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stores_count')
                    ->label(__('console.stores_count'))
                    ->alignCenter(),

                IconColumn::make('active')
                    ->label(__('console.active'))
                    ->boolean()
                    ->trueIcon(Heroicon::Bolt),

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
            ->description(__('console.tables.description.resellers'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.resellers'))
            ->emptyStateDescription(__('console.tables.empty_state.description.resellers'))
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
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

                    SelectFilter::make('subscription_health')
                        ->label(__('console.subscription_status'))
                        ->options([
                            'active'        => __('console.status_active'),
                            'expiring_soon' => __('console.expiring_soon'),
                            'none'          => __('console.no_active_subscription'),
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return match ($data['value'] ?? null) {
                                'active' => $query->whereHas(
                                    'subscriptions',
                                    fn(Builder $query): Builder => $query->active(),
                                ),
                                'expiring_soon' => $query->whereHas(
                                    'subscriptions',
                                    fn(Builder $query): Builder => $query->expiringWithin(7),
                                ),
                                'none' => $query->whereDoesntHave(
                                    'subscriptions',
                                    fn(Builder $query): Builder => $query->active(),
                                ),
                                default => $query,
                            };
                        }),

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ActionGroup::make(ResellerOperatorActions::owner())->dropdown(false),
                    ActionGroup::make(ResellerOperatorActions::subscription())->dropdown(false),
                    ActionGroup::make([OffboardResellerAction::make()])->dropdown(false),
                ]),
            ])
            ->recordUrl(fn(Reseller $record): string => ResellerResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    OffboardResellerBulkAction::make(),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
