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
                    ->placeholder(__('vendra-console::attributes.platform_owned_store')),

                TextColumn::make('description')
                    ->label(__('vendra-console::attributes.description'))
                    ->searchable()
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
                        ->getSearchResultsUsing(fn (string $search): array => Store::query()
                            ->withTrashed()
                            ->whereLike('name', "%{$search}%")
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
     * A store's name, resolved once per store per request.
     *
     * Every row carries a tenant key rather than a relation the console can
     * eager-load — the activity log is tenant-agnostic by design and names no
     * Store — so each distinct store on the page costs one lookup instead of
     * the console loading every store it has ever had.
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
