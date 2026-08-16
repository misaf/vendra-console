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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Models\Plan;

final class PlanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                BadgeableColumn::make('name')
                    ->label(__('console.name'))
                    ->icon(Heroicon::Tag)
                    ->searchable()
                    ->sortable()
                    ->prefixBadges([
                        Badge::make('is_default')
                            ->label(__('console.is_default'))
                            ->color('success')
                            ->size(Size::ExtraSmall)
                            ->hidden(fn(Plan $record): bool => ! $record->is_default),
                    ]),

                TextColumn::make('max_units')
                    ->label(__('console.max_units'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('period')
                    ->label(__('console.period'))
                    ->state(fn(Plan $record): string => "{$record->period_count} {$record->period_unit->value}"),

                TextColumn::make('price')
                    ->label(__('console.price'))
                    ->state(fn(Plan $record): string => $record->isFree()
                        ? __('console.free')
                        : $record->price . ' ' . ($record->currency_code ?? '')),

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
            ->description(__('console.tables.description.plans'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.plans'))
            ->emptyStateDescription(__('console.tables.empty_state.description.plans'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
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

                    TernaryFilter::make('is_default')
                        ->label(__('console.is_default'))
                        ->queries(
                            true: fn(Builder $query): Builder => $query->where('is_default', true),
                            false: fn(Builder $query): Builder => $query->where('is_default', false),
                            blank: fn(Builder $query): Builder => $query,
                        ),

                    SelectFilter::make('period_unit')
                        ->label(__('console.period_unit'))
                        ->options([
                            PeriodUnit::Day->value   => __('console.period_day'),
                            PeriodUnit::Week->value  => __('console.period_week'),
                            PeriodUnit::Month->value => __('console.period_month'),
                            PeriodUnit::Year->value  => __('console.period_year'),
                        ]),

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    DeleteAction::make()
                        ->hidden(fn(Plan $record): bool => $record->isInUse()),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
