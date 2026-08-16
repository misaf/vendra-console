<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Misaf\VendraProperty\Filament\Concerns\BuildsDailyTrend;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraTenant\Models\Tenant;

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

        return [
            Stat::make(__('console.resellers'), Reseller::query()->count())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->chart($this->dailyTrend(Reseller::query())),
            Stat::make(__('console.properties'), Tenant::query()->count())
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->chart($this->dailyTrend(Tenant::query())),
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
