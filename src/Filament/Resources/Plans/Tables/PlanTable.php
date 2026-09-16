<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Tables;

use Awcodes\BadgeableColumn\Components\Badge;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Tables\Columns\ActiveToggleColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;

final class PlanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                BadgeableColumn::make('name')
                    ->label(__('vendra-console::attributes.name'))
                    ->icon(Heroicon::Tag)
                    ->searchable()
                    ->sortable()
                    ->prefixBadges([
                        Badge::make('is_default')
                            ->label(__('vendra-console::attributes.is_default'))
                            ->color('success')
                            ->size(Size::ExtraSmall)
                            ->hidden(fn (Plan $record): bool => ! $record->is_default),
                    ]),

                TextColumn::make('max_units')
                    ->label(__('vendra-console::attributes.max_units'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('period')
                    ->label(__('vendra-console::attributes.period'))
                    ->state(fn (Plan $record): string => "{$record->period_count} ".__("vendra-console::attributes.period_{$record->period_unit->value}")),

                TextColumn::make('price')
                    ->label(__('vendra-console::attributes.price'))
                    ->state(fn (Plan $record): string => $record->isFree()
                        ? __('vendra-console::attributes.free')
                        : $record->price.' '.($record->currency_code ?? '')),

                ActiveToggleColumn::make()
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
                    TernaryFilter::make('active')
                        ->label(__('vendra-console::attributes.active'))
                        ->trueLabel(__('vendra-console::attributes.active'))
                        ->falseLabel(__('vendra-console::attributes.inactive'))
                        ->queries(
                            true: fn (Builder $query): Builder => $query->where('active', true),
                            false: fn (Builder $query): Builder => $query->where('active', false),
                            blank: fn (Builder $query): Builder => $query,
                        ),

                    TernaryFilter::make('is_default')
                        ->label(__('vendra-console::attributes.is_default'))
                        ->queries(
                            true: fn (Builder $query): Builder => $query->where('is_default', true),
                            false: fn (Builder $query): Builder => $query->where('is_default', false),
                            blank: fn (Builder $query): Builder => $query,
                        ),

                    SelectFilter::make('period_unit')
                        ->label(__('vendra-console::attributes.period_unit'))
                        ->options([
                            PeriodUnit::Day->value => __('vendra-console::attributes.period_day'),
                            PeriodUnit::Week->value => __('vendra-console::attributes.period_week'),
                            PeriodUnit::Month->value => __('vendra-console::attributes.period_month'),
                            PeriodUnit::Year->value => __('vendra-console::attributes.period_year'),
                        ]),

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
