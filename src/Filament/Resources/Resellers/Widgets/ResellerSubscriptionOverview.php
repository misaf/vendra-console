<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Support\ResellersOverPlan;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;

/**
 * Count resellers by subscription health above the reseller list; each stat
 * opens the list with the matching filter. Counts change slowly, so the widget
 * does not poll.
 */
final class ResellerSubscriptionOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $overPlan = count(resolve(ResellersOverPlan::class)->ids());

        return [
            self::stat(SubscriptionStatus::Active->getLabel(), Reseller::query()->withActiveSubscription()->count(), 'success', ['subscription_health' => ['value' => 'active']]),
            self::stat(__('vendra-console::attributes.expiring_soon'), Reseller::query()->withSubscriptionEndingWithin(7)->count(), 'warning', ['subscription_health' => ['value' => 'expiring_soon']]),
            self::stat(SubscriptionStatus::PastDue->getLabel(), Reseller::query()->withPastDueSubscription()->count(), 'danger', ['subscription_health' => ['value' => 'past_due']]),
            self::stat(__('vendra-console::attributes.no_active_subscription'), Reseller::query()->withoutActiveSubscription()->count(), 'gray', ['subscription_health' => ['value' => 'none']]),
            self::stat(__('vendra-console::attributes.over_plan'), $overPlan, 'danger', ['over_plan' => ['isActive' => true]]),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     */
    private static function stat(string $label, int $count, string $color, array $filters): Stat
    {
        return Stat::make($label, $count)
            ->color($count > 0 ? $color : 'gray')
            ->url(ResellerResource::getUrl('index', ['filters' => $filters]));
    }
}
