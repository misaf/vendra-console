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
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\StorefrontDeploymentActions;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;

final class StorefrontDeploymentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                TextColumn::make('store.name')
                    ->label(__('console.store'))
                    ->icon(Heroicon::GlobeAlt)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('console.status'))
                    ->badge()
                    ->formatStateUsing(fn(StorefrontDeploymentStatus $state): string => __("console.deployment_status_{$state->value}"))
                    ->sortable(),

                TextColumn::make('storefrontImage.name')
                    ->label(__('console.storefront_image'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('image')
                    ->label(__('console.storefront_image_reference'))
                    ->limit(40)
                    ->tooltip(fn(?string $state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('container_name')
                    ->label(__('console.container_name'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('requested_at')
                    ->label(__('console.requested_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('deployed_at')
                    ->label(__('console.deployed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('failed_at')
                    ->label(__('console.failed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sinceTooltip()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error')
                    ->label(__('console.failure_information'))
                    ->limit(60)
                    ->tooltip(fn(?string $state): ?string => $state)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->description(__('console.tables.description.storefront_deployments'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.storefront_deployments'))
            ->emptyStateDescription(__('console.tables.empty_state.description.storefront_deployments'))
            ->emptyStateIcon(Heroicon::OutlinedRocketLaunch)
            ->filters(
                [
                    SelectFilter::make('status')
                        ->label(__('console.status'))
                        ->options(self::statusOptions()),

                    SelectFilter::make('store_id')
                        ->label(__('console.store'))
                        ->relationship('store', 'name')
                        ->searchable()
                        ->preload(),

                    Filter::make('requested_at')
                        ->label(__('console.requested_at'))
                        ->schema([
                            DatePicker::make('from')
                                ->label(__('console.from_date')),
                            DatePicker::make('until')
                                ->label(__('console.until_date')),
                        ])
                        ->query(fn(Builder $query, array $data): Builder => $query
                            ->when($data['from'] ?? null, fn(Builder $query, string $date): Builder => $query->whereDate('requested_at', '>=', $date))
                            ->when($data['until'] ?? null, fn(Builder $query, string $date): Builder => $query->whereDate('requested_at', '<=', $date))),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        ViewAction::make(),
                        StorefrontDeploymentActions::logs(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        StorefrontDeploymentActions::retry(),
                        StorefrontDeploymentActions::reconcile(),
                        StorefrontDeploymentActions::restart(),
                    ])->dropdown(false),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(StorefrontDeploymentStatus::cases())
            ->mapWithKeys(fn(StorefrontDeploymentStatus $status): array => [
                $status->value => __("console.deployment_status_{$status->value}"),
            ])
            ->all();
    }
}
