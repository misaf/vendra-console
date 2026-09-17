<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Misaf\VendraConsole\Filament\Widgets\ContainerRuntimeHealth;
use Misaf\VendraConsole\Filament\Widgets\NeedsAttention;
use Misaf\VendraConsole\Filament\Widgets\PlatformGrowthChart;
use Misaf\VendraConsole\Filament\Widgets\PlatformMetrics;
use Misaf\VendraConsole\Filament\Widgets\RecentActivity;

/**
 * The console's home, ordered by urgency: what needs a console user now, the
 * platform's key numbers, how it is growing, the storefront runtime, and what
 * changed recently.
 */
final class Dashboard extends BaseDashboard
{
    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [
            NeedsAttention::class,
            PlatformMetrics::class,
            PlatformGrowthChart::class,
            ContainerRuntimeHealth::class,
            RecentActivity::class,
        ];
    }

    public function getColumns(): int
    {
        return 1;
    }
}
