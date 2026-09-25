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
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Support\ContainsSearch;
use Misaf\VendraSupport\Tenancy\TenantSchema;

final class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        $tenantColumn = TenantSchema::column();

        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('store')
                    ->label(__('vendra-console::navigation.store'))
                    ->icon(Heroicon::GlobeAlt)
                    ->state(fn (ActivityLog $record): ?string => self::storeName($record->getAttribute(TenantSchema::column())))
                    ->placeholder(__('vendra-console::attributes.platform')),

                ...self::activityColumns(searchable: true),

                CreatedAtColumn::make()
                    ->sortable(),
            ])
            ->description(__('vendra-console::tables.description.activity_logs'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.activity_logs'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.activity_logs'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->filters(
                [
                    SelectFilter::make($tenantColumn)
                        ->label(__('vendra-console::navigation.store'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => ContainsSearch::apply(Store::query()->withTrashed(), ['name'], $search)
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => self::storeName($value)),

                    SelectFilter::make('event')
                        ->label(__('vendra-console::attributes.event'))
                        ->options(fn (): array => ActivityLog::query()
                            ->whereNotNull('event')
                            ->distinct()
                            ->pluck('event', 'event')
                            ->all()),

                    Filter::make('platform')
                        ->label(__('vendra-console::attributes.platform_activity'))
                        ->query(fn (Builder $query): Builder => $query->whereNull(TenantSchema::column())),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->defaultSort(column: 'id', direction: 'desc');
    }

    /**
     * Get the columns that describe an entry, shared with the dashboard's recent activity.
     *
     * @return list<TextColumn>
     */
    public static function activityColumns(bool $searchable = false): array
    {
        return [
            TextColumn::make('description')
                ->label(__('vendra-console::attributes.description'))
                ->searchable($searchable)
                ->wrap(),

            TextColumn::make('event')
                ->label(__('vendra-console::attributes.event'))
                ->badge()
                ->placeholder('—'),

            TextColumn::make('subject_type')
                ->label(__('vendra-console::attributes.subject'))
                ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                ->description(fn (ActivityLog $record): ?string => $record->subject_id === null
                    ? null
                    : '#'.$record->subject_id)
                ->placeholder('—'),
        ];
    }

    /**
     * Get a store's name, memoized per request.
     *
     * The activity log has no store relation to eager load.
     */
    private static function storeName(mixed $storeId): ?string
    {
        if (! is_numeric($storeId)) {
            return null;
        }

        return once(function () use ($storeId): ?string {
            $name = Store::query()->withTrashed()->whereKey((int) $storeId)->value('name');

            return is_string($name) ? $name : null;
        });
    }
}
