<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\CancelSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangePlanTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangeUserEmailTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangeUserPasswordTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ExtendSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\OffboardResellerBulkAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\OffboardResellerTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ReactivateSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\RenewSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ReplaceUserAccountTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Actions\SetResellerActiveAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveToggleColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Filters\IsActiveFilter;

final class ResellerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('user.username')
                    ->label(__('vendra-console::attributes.username'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label(__('vendra-console::attributes.email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stores_count')
                    ->label(__('vendra-console::attributes.stores_count'))
                    ->alignCenter(),

                IsActiveToggleColumn::make()
                    ->disabled(fn (Reseller $record): bool => $record->trashed())
                    ->updateStateUsing(fn (Reseller $record, bool $state, SetResellerActiveAction $setActive): bool => $setActive->execute($record, $state)->active),

                CreatedAtColumn::make()
                    ->sortable(),

                UpdatedAtColumn::make(),
            ])
            ->description(__('vendra-console::tables.description.resellers'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.resellers'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.resellers'))
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->filters(
                [
                    IsActiveFilter::make(),

                    SelectFilter::make('subscription_health')
                        ->label(__('vendra-console::attributes.subscription_status'))
                        ->options([
                            'active' => SubscriptionStatus::Active->getLabel(),
                            'expiring_soon' => __('vendra-console::attributes.expiring_soon'),
                            'none' => __('vendra-console::attributes.no_active_subscription'),
                        ])
                        ->query(fn (Builder $query, array $data): Builder => match (Arr::get($data, 'value', null)) {
                            'active' => $query->whereHas(
                                'subscriptions',
                                fn (Builder $query): Builder => $query->active(),
                            ),
                            'expiring_soon' => $query->whereHas(
                                'subscriptions',
                                fn (Builder $query): Builder => $query->endingWithin(7),
                            ),
                            'none' => $query->whereDoesntHave(
                                'subscriptions',
                                fn (Builder $query): Builder => $query->active(),
                            ),
                            default => $query,
                        }),

                    TrashedFilter::make(),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ActionGroup::make([
                        ChangeUserPasswordTableAction::make(),
                        ChangeUserEmailTableAction::make(),
                        ReplaceUserAccountTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        ChangePlanTableAction::make(),
                        RenewSubscriptionTableAction::make(),
                        ExtendSubscriptionTableAction::make(),
                        CancelSubscriptionTableAction::make(),
                        ReactivateSubscriptionTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([OffboardResellerTableAction::make()])->dropdown(false),
                ]),
            ])
            ->recordUrl(fn (Reseller $record): string => ResellerResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    OffboardResellerBulkAction::make(),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
