<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StoreStatusCounts;
use Misaf\VendraSubscription\Enums\SubscriptionPaymentStatus;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Models\SubscriptionPayment;

final class NeedsAttention extends StatsOverviewWidget
{
    private const array PAYMENT_STATUSES_NEEDING_REVIEW = [
        SubscriptionPaymentStatus::RequiresAction,
        SubscriptionPaymentStatus::NeedsReconciliation,
        SubscriptionPaymentStatus::RefundFailed,
    ];

    protected ?string $pollingInterval = '60s';

    protected function getHeading(): string
    {
        return __('vendra-console::attributes.needs_attention');
    }

    protected function getStats(): array
    {
        $storesNeedingAttention = StoreStatusCounts::for()->needingAttention();
        $failedDeployments = StorefrontDeployment::query()->where('status', StorefrontDeploymentStatus::Failed)->count();
        $pastDueSubscriptions = Subscription::query()->where('status', SubscriptionStatus::PastDue)->count();
        $endingSoon = Subscription::query()->endingWithin(7)->count();
        $paymentsNeedingReview = SubscriptionPayment::query()->whereIn('status', self::PAYMENT_STATUSES_NEEDING_REVIEW)->count();
        $failedJobs = self::recentlyFailedJobs();

        $stats = array_filter([
            $storesNeedingAttention > 0 ? Stat::make(__('vendra-console::attributes.stores_needing_attention'), $storesNeedingAttention)
                ->description(__('vendra-console::attributes.stores_needing_attention_description'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(StoreResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [
                            StoreStatus::Failed->value,
                            StoreStatus::Pending->value,
                            StoreStatus::Provisioning->value,
                        ]],
                    ],
                ])) : null,
            $failedDeployments > 0 ? Stat::make(__('vendra-console::attributes.failed_deployments'), $failedDeployments)
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->color('danger')
                ->url(StorefrontDeploymentResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => StorefrontDeploymentStatus::Failed->value]],
                ])) : null,
            $pastDueSubscriptions > 0 ? Stat::make(__('vendra-console::attributes.past_due_subscriptions'), $pastDueSubscriptions)
                ->icon(Heroicon::OutlinedCreditCard)
                ->color('danger')
                ->url(ResellerResource::getUrl('index', [
                    'tableFilters' => ['subscription_health' => ['value' => 'past_due']],
                ])) : null,
            $paymentsNeedingReview > 0 ? Stat::make(__('vendra-console::attributes.payments_needing_review'), $paymentsNeedingReview)
                ->description(__('vendra-console::attributes.payments_needing_review_description'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning') : null,
            $endingSoon > 0 ? Stat::make(__('vendra-console::attributes.expiring_soon'), $endingSoon)
                ->icon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url(ResellerResource::getUrl('index', [
                    'tableFilters' => ['subscription_health' => ['value' => 'expiring_soon']],
                ])) : null,
            $failedJobs > 0 ? Stat::make(__('vendra-console::attributes.failed_jobs'), $failedJobs)
                ->description(__('vendra-console::attributes.failed_jobs_description'))
                ->icon(Heroicon::OutlinedQueueList)
                ->color('danger') : null,
        ]);

        if ($stats === []) {
            return [
                Stat::make(__('vendra-console::attributes.needs_attention_all_clear'), '0')
                    ->description(__('vendra-console::attributes.needs_attention_all_clear_description'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success'),
            ];
        }

        return array_values($stats);
    }

    private static function recentlyFailedJobs(): int
    {
        return DB::connection(Config::string('queue.failed.database'))
            ->table(Config::string('queue.failed.table', 'failed_jobs'))
            ->where('failed_at', '>=', now()->subDay())
            ->count();
    }
}
