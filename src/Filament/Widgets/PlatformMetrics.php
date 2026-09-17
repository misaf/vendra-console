<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Carbon\CarbonInterface;
use Cknow\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Filament\Concerns\BuildsDailyTrend;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Support\StoreStatusCounts;
use Misaf\VendraSubscription\Enums\SubscriptionPaymentStatus;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Models\SubscriptionPayment;
use Throwable;

/**
 * The platform's key numbers. They change slowly, so the widget does not poll.
 */
final class PlatformMetrics extends StatsOverviewWidget
{
    use BuildsDailyTrend;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stores = StoreStatusCounts::for();

        return [
            Stat::make(__('vendra-console::navigation.stores'), $stores->total())
                ->description(__('vendra-console::attributes.stores_active_suspended', [
                    'active' => $stores->count(StoreStatus::Active),
                    'suspended' => $stores->count(StoreStatus::Suspended),
                ]))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->url(StoreResource::getUrl('index'))
                ->chart($this->dailyTrend(Store::query())),
            Stat::make(__('vendra-console::attributes.active_resellers'), Reseller::query()->active()->count())
                ->description(__('vendra-console::attributes.resellers_total', ['count' => Reseller::query()->count()]))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->url(ResellerResource::getUrl('index'))
                ->chart($this->dailyTrend(Reseller::query())),
            Stat::make(__('vendra-console::attributes.active_subscriptions'), Subscription::query()->active()->count())
                ->icon(Heroicon::OutlinedCheckBadge)
                ->url(ResellerResource::getUrl('index', [
                    'tableFilters' => ['subscription_health' => ['value' => 'active']],
                ]))
                ->chart($this->dailyTrend(Subscription::query(), 'starts_at')),
            Stat::make(
                __('vendra-console::attributes.revenue_this_month'),
                self::revenueBetween(now()->startOfMonth(), now()) ?? __('vendra-console::attributes.no_revenue'),
            )
                ->description(__('vendra-console::attributes.revenue_last_month', [
                    'amount' => self::revenueBetween(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth())
                        ?? __('vendra-console::attributes.no_revenue'),
                ]))
                ->icon(Heroicon::OutlinedBanknotes),
        ];
    }

    /**
     * Paid subscription revenue in the window, one formatted amount per currency.
     * Null when nothing was paid.
     */
    private static function revenueBetween(CarbonInterface $from, CarbonInterface $until): ?string
    {
        $totals = SubscriptionPayment::query()
            ->where('status', SubscriptionPaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $until])
            ->selectRaw('currency_code, sum(amount) as total')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->toBase()
            ->get();

        $amounts = [];

        foreach ($totals as $row) {
            if (is_string($row->currency_code) && is_numeric($row->total)) {
                $amounts[] = self::formatMoney((int) $row->total, $row->currency_code);
            }
        }

        return $amounts === [] ? null : implode(' · ', $amounts);
    }

    private static function formatMoney(int $amount, string $currencyCode): string
    {
        try {
            return new Money($amount, $currencyCode)->format();
        } catch (Throwable) {
            return $amount.' '.$currencyCode;
        }
    }
}
