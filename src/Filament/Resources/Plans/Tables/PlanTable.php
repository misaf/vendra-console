<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveToggleColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsDefaultIconColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Filters\IsActiveFilter;
use Misaf\VendraSupport\Filament\Tables\Filters\IsDefaultFilter;

final class PlanTable
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

                IsDefaultIconColumn::make(),

                TextColumn::make('max_units')
                    ->label(__('vendra-console::attributes.max_units'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('period')
                    ->label(__('vendra-console::attributes.period'))
                    ->state(fn (Plan $record): string => "{$record->period_count} ".$record->period_unit->getLabel()),

                TextColumn::make('price')
                    ->label(__('vendra-console::attributes.price'))
                    ->state(fn (Plan $record): string => $record->isFree()
                        ? __('vendra-console::attributes.free')
                        : $record->price.' '.($record->currency_code ?? '')),

                IsActiveToggleColumn::make()
                    ->updateStateUsing(function (Plan $record, bool $state, UpdatePlanAction $updatePlan): bool {
                        $updatePlan->execute($record, ['active' => $state]);

                        return $state;
                    }),

                CreatedAtColumn::make()
                    ->sortable(),

                UpdatedAtColumn::make(),
            ])
            ->description(__('vendra-console::tables.description.plans'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.plans'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.plans'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->filters(
                [
                    IsActiveFilter::make(),

                    IsDefaultFilter::make(),

                    SelectFilter::make('period_unit')
                        ->label(__('vendra-console::attributes.period_unit'))
                        ->options(PeriodUnit::class),

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    DeleteAction::make()
                        ->hidden(fn (Plan $record): bool => $record->isInUse())
                        ->using(function (Plan $record, DeletePlanAction $deletePlan): bool {
                            $deletePlan->execute($record);

                            return true;
                        }),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
