<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Misaf\VendraStore\Support\StorefrontNetwork;
use Misaf\VendraStore\Support\StorefrontRuntimeHealth;
use Misaf\VendraStore\Support\StorefrontRuntimeHealthReport;

/**
 * Only the worker can reach the runtime, so a missing or stale report is a warning.
 */
final class ContainerRuntimeHealth extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $report = resolve(StorefrontRuntimeHealth::class)->latest();

        if (! $report instanceof StorefrontRuntimeHealthReport) {
            return [
                Stat::make(__('vendra-console::attributes.container_runtime'), __('vendra-console::attributes.unknown'))
                    ->description(__('vendra-console::messages.runtime_not_checked'))
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('warning'),
            ];
        }

        return [
            $this->runtimeStat($report),
            $this->networkStat($report),
        ];
    }

    private function runtimeStat(StorefrontRuntimeHealthReport $report): Stat
    {
        $status = $report->status;

        $description = match (true) {
            $report->isStale() => __('vendra-console::messages.runtime_report_stale', ['time' => $report->checkedAt->diffForHumans()]),
            ! $status->reachable => $status->message ?? __('vendra-console::messages.runtime_unavailable'),
            $status->engineMismatch() => __('vendra-console::messages.runtime_engine_mismatch', [
                'configured' => $status->driver,
                'reported' => $status->reportedEngine() ?? __('vendra-console::attributes.unknown'),
            ]),
            default => __('vendra-console::messages.runtime_connected', [
                'api' => $status->apiVersion,
                'version' => $status->server ?? __('vendra-console::attributes.unknown'),
            ]),
        };

        return Stat::make(__('vendra-console::attributes.container_runtime'), Str::headline($status->driver))
            ->description($description)
            ->icon(Heroicon::OutlinedServerStack)
            ->color(match (true) {
                $report->isStale() => 'warning',
                ! $status->reachable, $status->engineMismatch() => 'danger',
                default => 'success',
            });
    }

    private function networkStat(StorefrontRuntimeHealthReport $report): Stat
    {
        return Stat::make(__('vendra-console::attributes.storefront_network'), $report->networkName)
            ->description($this->networkDescription($report))
            ->icon(Heroicon::OutlinedShare)
            ->color(match (true) {
                $report->isStale() => 'warning',
                $report->status->reachable && $report->network instanceof StorefrontNetwork => 'success',
                default => 'danger',
            });
    }

    private function networkDescription(StorefrontRuntimeHealthReport $report): string
    {
        if (! $report->status->reachable) {
            return __('vendra-console::messages.network_not_checked');
        }

        if ($report->networkError !== null) {
            return $report->networkError;
        }

        if (! $report->network instanceof StorefrontNetwork) {
            return __('vendra-console::messages.network_unavailable');
        }

        return __('vendra-console::messages.network_available', [
            'driver' => $report->network->driver ?? __('vendra-console::attributes.unknown'),
        ]);
    }
}
