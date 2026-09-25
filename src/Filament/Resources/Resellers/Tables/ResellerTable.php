<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ActivateResellerTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\CancelSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangePlanTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangeUserEmailTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ChangeUserPasswordTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\CreditWalletTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\DeactivateResellerTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ExtendSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\OffboardResellerBulkAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\OffboardResellerTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ReactivateSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\RenewSubscriptionTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ReplaceUserAccountTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\ResetUserTwoFactorTableAction;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Support\ResellersOverPlan;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSupport\Enums\PlanFeature;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Filters\IsActiveFilter;
use Misaf\VendraUser\Filament\Tables\Columns\EmailColumn;
use Misaf\VendraUser\Filament\Tables\Columns\EmailVerifiedAtColumn;
use Misaf\VendraUser\Filament\Tables\Columns\UsernameColumn;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\EmailConstraint;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\EmailVerifiedAtConstraint;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\UsernameConstraint;

final class ResellerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                UsernameColumn::make('user.username')
                    ->sortable(),

                EmailColumn::make('user.email')
                    ->sortable(),

                EmailVerifiedAtColumn::make('user.email_verified_at'),

                TextColumn::make('stores_count')
                    ->label(__('vendra-console::attributes.stores_count'))
                    ->alignCenter(),

                IconColumn::make('has_priority_support')
                    ->label(PlanFeature::PrioritySupport->getLabel())
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                IsActiveIconColumn::make(),

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
                            'past_due' => SubscriptionStatus::PastDue->getLabel(),
                            'none' => __('vendra-console::attributes.no_active_subscription'),
                        ])
                        ->query(fn (Builder $query, array $data): Builder => self::filterBySubscription($query, Arr::get($data, 'value', null))),

                    Filter::make('priority_support')
                        ->label(PlanFeature::PrioritySupport->getLabel())
                        ->toggle()
                        ->query(fn (Builder $query): Builder => self::filterByPrioritySupport($query)),

                    Filter::make('over_plan')
                        ->label(__('vendra-console::attributes.over_plan'))
                        ->toggle()
                        ->query(fn (Builder $query): Builder => $query->whereKey(resolve(ResellersOverPlan::class)->ids())),

                    TrashedFilter::make(),

                    QueryBuilder::make()
                        ->constraints([
                            UsernameConstraint::make('user.username'),
                            EmailConstraint::make('user.email'),
                            EmailVerifiedAtConstraint::make('user.email_verified_at'),
                        ]),
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
                        ResetUserTwoFactorTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        ChangePlanTableAction::make(),
                        RenewSubscriptionTableAction::make(),
                        ExtendSubscriptionTableAction::make(),
                        CancelSubscriptionTableAction::make(),
                        ReactivateSubscriptionTableAction::make(),
                        CreditWalletTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        DeactivateResellerTableAction::make(),
                        ActivateResellerTableAction::make(),
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

    /**
     * @param  Builder<Reseller>  $query
     * @return Builder<Reseller>
     */
    private static function filterByPrioritySupport(Builder $query): Builder
    {
        return $query->withPlanFeature(PlanFeature::PrioritySupport);
    }

    /**
     * @param  Builder<Reseller>  $query
     * @return Builder<Reseller>
     */
    private static function filterBySubscription(Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'active' => $query->withActiveSubscription(),
            'expiring_soon' => $query->withSubscriptionEndingWithin(7),
            'past_due' => $query->withPastDueSubscription(),
            'none' => $query->withoutActiveSubscription(),
            default => $query,
        };
    }
}
