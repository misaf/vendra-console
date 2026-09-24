<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ReconcileDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RestartDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RetryDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ViewLogsTableAction;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;

final class StorefrontDeploymentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('store.name')
                    ->label(__('vendra-console::navigation.store'))
                    ->icon(Heroicon::GlobeAlt)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('vendra-console::attributes.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('storefrontImage.image')
                    ->label(__('vendra-console::navigation.storefront_image'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('image')
                    ->label(__('vendra-console::attributes.storefront_image_reference'))
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('container_name')
                    ->label(__('vendra-console::attributes.container_name'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('requested_at')
                    ->label(__('vendra-console::attributes.requested_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('deployed_at')
                    ->label(__('vendra-console::attributes.deployed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('failed_at')
                    ->label(__('vendra-console::attributes.failed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error')
                    ->label(__('vendra-console::attributes.failure_information'))
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->description(__('vendra-console::tables.description.storefront_deployments'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.storefront_deployments'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.storefront_deployments'))
            ->emptyStateIcon(Heroicon::OutlinedRocketLaunch)
            ->filters(
                [
                    SelectFilter::make('status')
                        ->label(__('vendra-console::attributes.status'))
                        ->options(StorefrontDeploymentStatus::class),

                    SelectFilter::make('store_id')
                        ->label(__('vendra-console::navigation.store'))
                        ->relationship('store', 'name')
                        ->searchable()
                        ->preload(),

                    Filter::make('requested_at')
                        ->label(__('vendra-console::attributes.requested_at'))
                        ->schema([
                            DatePicker::make('from')
                                ->label(__('vendra-console::attributes.from_date')),
                            DatePicker::make('until')
                                ->label(__('vendra-console::attributes.until_date')),
                        ])
                        ->query(fn (Builder $query, array $data): Builder => self::filterByRequestedDate($query, Arr::get($data, 'from', null), Arr::get($data, 'until', null))),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        ViewAction::make(),
                        ViewLogsTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        RetryDeploymentTableAction::make(),
                        ReconcileDeploymentTableAction::make(),
                        RestartDeploymentTableAction::make(),
                    ])->dropdown(false),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }

    /**
     * @param  Builder<StorefrontDeployment>  $query
     * @return Builder<StorefrontDeployment>
     */
    private static function filterByRequestedDate(Builder $query, mixed $from, mixed $until): Builder
    {
        return $query->requestedBetween(
            is_string($from) && $from !== '' ? $from : null,
            is_string($until) && $until !== '' ? $until : null,
        );
    }
}
