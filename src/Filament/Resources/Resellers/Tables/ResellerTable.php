<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerBulkAction;

final class ResellerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->withCount('tenants'))
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

                TextColumn::make('tenants_count')
                    ->label(__('console.properties_count'))
                    ->alignCenter(),

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

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    OffboardResellerAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    OffboardResellerBulkAction::make(),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
