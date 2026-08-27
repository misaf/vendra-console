<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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
        $expiringSoon = Subscription::query()
            ->active()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
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
                ->chart($this->dailyTrend(Reseller::query())),

            Stat::make(__('console.stores'), Store::query()->count())
                ->description(__('console.stores_active_suspended', [
                    'active'    => $activeStores,
                    'suspended' => $suspendedStores,
                ]))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->chart($this->dailyTrend(Store::query())),

            /*
             | Provisioning is the queue an operator can still act on; failures
             | are the ones that stopped moving on their own. Kept in one stat so
             | the dashboard shows both halves of "is anything stuck".
             */
            Stat::make(__('console.provisioning'), $provisioningStores)
                ->description(__('console.failed_stores') . ': ' . $failedStores)
                ->icon(Heroicon::OutlinedArrowPath)
                ->color($failedStores > 0 ? 'danger' : ($provisioningStores > 0 ? 'warning' : 'gray')),

            Stat::make(__('console.storefronts_live'), $liveDeployments)
                ->description(__('console.failed_deployments') . ': ' . $failedDeployments)
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color($failedDeployments > 0 ? 'danger' : 'success'),

            Stat::make(__('console.active_subscriptions'), Subscription::query()->active()->count())
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->chart($this->dailyTrend(Subscription::query(), 'starts_at')),

            Stat::make(__('console.expiring_soon'), $expiringSoon)
                ->icon(Heroicon::OutlinedClock)
                ->color($expiringSoon > 0 ? 'warning' : 'gray'),
        ];
    }
}
