<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Filament\Concerns\BuildsDailyTrend;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraSubscription\Models\Subscription;

final class ConsoleOverview extends StatsOverviewWidget
{
    use BuildsDailyTrend;

    protected function getStats(): array
    {
        $expiringSoon = Reseller::query()
            ->whereHas('subscriptions', fn(Builder $query): Builder => $query->expiringWithin(7))
            ->count();

        $activeSubscriptions = Reseller::query()
            ->whereHas('subscriptions', fn(Builder $query): Builder => $query->active())
            ->count();

        $activeStores = Store::query()->withStatus(StoreStatus::Active)->count();
        $suspendedStores = Store::query()->withStatus(StoreStatus::Suspended)->count();
        $failedStores = Store::query()->withStatus(StoreStatus::Failed)->count();
        $provisioningStores = Store::query()->withStatus(StoreStatus::Pending)->count()
            + Store::query()->withStatus(StoreStatus::Provisioning)->count();

        $failedDeployments = StorefrontDeployment::query()
            ->where('status', StorefrontDeploymentStatus::Failed)
            ->count();
        $liveDeployments = StorefrontDeployment::query()
            ->where('status', StorefrontDeploymentStatus::Ready)
            ->count();

        return [
            Stat::make(__('console.resellers'), Reseller::query()->count())
                ->description(__('console.active_resellers') . ': ' . Reseller::query()->active()->count())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->url(ResellerResource::getUrl('index'))
                ->chart($this->dailyTrend(Reseller::query())),

            Stat::make(__('console.stores'), Store::query()->count())
                ->description(__('console.stores_active_suspended', [
                    'active'    => $activeStores,
                    'suspended' => $suspendedStores,
                ]))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->url(StoreResource::getUrl('index'))
                ->chart($this->dailyTrend(Store::query())),

            Stat::make(__('console.provisioning'), $provisioningStores)
                ->description(__('console.failed_stores') . ': ' . $failedStores)
                ->icon(Heroicon::OutlinedArrowPath)
                ->color($failedStores > 0 ? 'danger' : ($provisioningStores > 0 ? 'warning' : 'gray'))
                ->url(StoreResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [StoreStatus::Pending->value, StoreStatus::Provisioning->value]],
                    ],
                ])),

            Stat::make(__('console.failed_stores'), $failedStores)
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->color($failedStores > 0 ? 'danger' : 'gray')
                ->url(StoreResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [StoreStatus::Failed->value]],
                    ],
                ])),

            Stat::make(__('console.storefronts_live'), $liveDeployments)
                ->description(__('console.failed_deployments') . ': ' . $failedDeployments)
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('success')
                ->url(StorefrontDeploymentResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => StorefrontDeploymentStatus::Ready->value],
                    ],
                ])),

            Stat::make(__('console.failed_deployments'), $failedDeployments)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($failedDeployments > 0 ? 'danger' : 'gray')
                ->url(StorefrontDeploymentResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => StorefrontDeploymentStatus::Failed->value],
                    ],
                ])),

            Stat::make(__('console.active_subscriptions'), $activeSubscriptions)
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->url(ResellerResource::getUrl('index', [
                    'tableFilters' => ['subscription_health' => ['value' => 'active']],
                ]))
                ->chart($this->dailyTrend(Subscription::query(), 'starts_at')),

            Stat::make(__('console.expiring_soon'), $expiringSoon)
                ->icon(Heroicon::OutlinedClock)
                ->color($expiringSoon > 0 ? 'warning' : 'gray')
                ->url(ResellerResource::getUrl('index', [
                    'tableFilters' => ['subscription_health' => ['value' => 'expiring_soon']],
                ])),
        ];
    }
}
