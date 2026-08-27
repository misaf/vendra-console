<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\ActivityLogs\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSupport\Tenancy\TenantSchema;

final class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        $tenantColumn = TenantSchema::column();

        return $table
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                TextColumn::make('store')
                    ->label(__('console.store'))
                    ->icon(Heroicon::GlobeAlt)
                    ->state(fn(ActivityLog $record): ?string => self::storeNames()->get($record->getAttribute(TenantSchema::column())))
                    ->placeholder(__('console.platform_owned_store')),

                TextColumn::make('description')
                    ->label(__('console.description'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('event')
                    ->label(__('console.event'))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('subject_type')
                    ->label(__('console.subject'))
                    ->formatStateUsing(fn(?string $state): string => null === $state ? '—' : class_basename($state))
                    ->description(fn(ActivityLog $record): ?string => null === $record->subject_id
                        ? null
                        : '#' . $record->subject_id)
                    ->placeholder('—'),

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
            ])
            ->description(__('console.tables.description.activity_logs'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.activity_logs'))
            ->emptyStateDescription(__('console.tables.empty_state.description.activity_logs'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->filters(
                [
                    SelectFilter::make($tenantColumn)
                        ->label(__('console.store'))
                        ->options(fn(): array => self::storeNames()->all()),

                    SelectFilter::make('event')
                        ->label(__('console.event'))
                        ->options(fn(): array => ActivityLog::query()
                            ->whereNotNull('event')
                            ->distinct()
                            ->pluck('event', 'event')
                            ->all()),

                    Filter::make('platform')
                        ->label(__('console.platform_activity'))
                        ->query(fn(Builder $query): Builder => $query->whereNull(TenantSchema::column())),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->defaultSort(column: 'id', direction: 'desc');
    }

    /**
     * Store names keyed by id, resolved once per request.
     *
     * Every row carries a tenant key rather than a relation the console can
     * eager-load — the activity log is tenant-agnostic by design and names no
     * Store — so the mapping is built here instead of per row.
     *
     * @return Collection<int, string>
     */
    private static function storeNames(): Collection
    {
        return once(fn(): Collection => Store::query()
            ->withTrashed()
            ->get(['id', 'name'])
            ->mapWithKeys(fn(Store $store): array => [$store->id => $store->name]));
    }
}
