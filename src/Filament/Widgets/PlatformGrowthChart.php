<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Concerns\BuildsDailyTrend;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSubscription\Models\Subscription;

final class PlatformGrowthChart extends ChartWidget
{
    use BuildsDailyTrend;

    private const array WINDOWS = [7, 30, 90];

    public ?string $filter = '30';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('vendra-console::attributes.platform_growth');
    }

    /**
     * Get the filters, keyed by the window in days.
     *
     * @return array<int, string>
     */
    protected function getFilters(): array
    {
        $filters = [];

        foreach (self::WINDOWS as $days) {
            $filters[$days] = __('vendra-console::attributes.last_days', ['days' => $days]);
        }

        return $filters;
    }

    /**
     * Count creations, including records offboarded since, so history never shrinks.
     *
     * @return array{datasets: list<array{label: string, data: list<float>}>, labels: list<string>}
     */
    protected function getData(): array
    {
        $days = in_array((int) $this->filter, self::WINDOWS, true) ? (int) $this->filter : 30;

        $labels = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $labels[] = now()->subDays($offset)->translatedFormat('M j');
        }

        return [
            'datasets' => [
                ['label' => __('vendra-console::attributes.new_stores'), 'data' => $this->dailyTrend(Store::query()->withTrashed(), days: $days)],
                ['label' => __('vendra-console::attributes.new_resellers'), 'data' => $this->dailyTrend(Reseller::query()->withTrashed(), days: $days)],
                ['label' => __('vendra-console::attributes.subscriptions_started'), 'data' => $this->dailyTrend(Subscription::query()->withTrashed(), 'starts_at', $days)],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
