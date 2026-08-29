<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Misaf\VendraStore\Services\StorefrontContainerRuntime;
use Misaf\VendraStore\Support\StorefrontNetwork;
use Misaf\VendraStore\Support\StorefrontRuntimeStatus;
use Misaf\VendraStore\Support\StorefrontSettings;
use Throwable;

final class ContainerRuntimeHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        try {
            $runtime = app(StorefrontContainerRuntime::class);
            $status = $runtime->status();
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make(__('console.container_runtime'), __('console.unknown'))
                    ->description($exception->getMessage())
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('danger'),
            ];
        }

        return [
            $this->runtimeStat($status),
            $this->networkStat($runtime, $status),
        ];
    }

    private function runtimeStat(StorefrontRuntimeStatus $status): Stat
    {
        $description = match (true) {
            ! $status->reachable      => $status->message ?? __('console.runtime_unavailable'),
            $status->engineMismatch() => __('console.runtime_engine_mismatch', [
                'configured' => $status->driver,
                'reported'   => $status->reportedEngine() ?? __('console.unknown'),
            ]),
            default => __('console.runtime_connected', [
                'api'     => $status->apiVersion,
                'version' => $status->server ?? __('console.unknown'),
            ]),
        };

        return Stat::make(__('console.container_runtime'), Str::headline($status->driver))
            ->description($description)
            ->icon(Heroicon::OutlinedServerStack)
            ->color(match (true) {
                ! $status->reachable, $status->engineMismatch()  => 'danger',
                default                                          => 'success',
            });
    }

    private function networkStat(StorefrontContainerRuntime $runtime, StorefrontRuntimeStatus $status): Stat
    {
        $networkName = app(StorefrontSettings::class)->network;
        $network = null;
        $error = null;

        if ($status->reachable) {
            try {
                $network = $runtime->findNetwork($networkName);
            } catch (Throwable $exception) {
                report($exception);
                $error = $exception->getMessage();
            }
        }

        return Stat::make(__('console.storefront_network'), $networkName)
            ->description($this->networkDescription($status, $network, $error))
            ->icon(Heroicon::OutlinedShare)
            ->color($status->reachable && $network instanceof StorefrontNetwork ? 'success' : 'danger');
    }

    private function networkDescription(StorefrontRuntimeStatus $status, ?StorefrontNetwork $network, ?string $error): string
    {
        if ( ! $status->reachable) {
            return __('console.network_not_checked');
        }

        if (null !== $error) {
            return $error;
        }

        if ( ! $network instanceof StorefrontNetwork) {
            return __('console.network_unavailable');
        }

        return __('console.network_available', [
            'driver' => $network->driver ?? __('console.unknown'),
        ]);
    }
}
