<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Misaf\VendraContainer\Contracts\ContainerRuntime;
use Misaf\VendraContainer\Support\ContainerRuntimeConfiguration;
use Misaf\VendraContainer\ValueObjects\NetworkInfo;
use Misaf\VendraContainer\ValueObjects\RuntimeStatus;
use Misaf\VendraStore\Support\StorefrontSettings;
use Throwable;

final class ContainerRuntimeHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $configuration = app(ContainerRuntimeConfiguration::class);

        if ( ! $configuration->isConfigured()) {
            return [
                Stat::make(__('console.container_runtime'), Str::headline($configuration->runtime))
                    ->description(__('console.runtime_not_configured'))
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('danger'),
            ];
        }

        try {
            $runtime = app(ContainerRuntime::class);
            $status = $runtime->ping();
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make(__('console.container_runtime'), Str::headline($configuration->runtime))
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

    private function runtimeStat(RuntimeStatus $status): Stat
    {
        $description = match (true) {
            ! $status->reachable      => $status->message ?? __('console.runtime_unavailable'),
            $status->engineMismatch() => __('console.runtime_engine_mismatch', [
                'configured' => $status->runtime,
                'reported'   => $status->reportedEngine()?->value ?? __('console.unknown'),
            ]),
            default => __('console.runtime_connected', [
                'api'     => $status->apiVersion,
                'version' => $status->version ?? __('console.unknown'),
            ]),
        };

        return Stat::make(__('console.container_runtime'), Str::headline($status->runtime))
            ->description($description)
            ->icon(Heroicon::OutlinedServerStack)
            ->color(match (true) {
                ! $status->reachable, $status->engineMismatch()  => 'danger',
                default                                          => 'success',
            });
    }

    private function networkStat(ContainerRuntime $runtime, RuntimeStatus $status): Stat
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
            ->color($status->reachable && $network instanceof NetworkInfo ? 'success' : 'danger');
    }

    private function networkDescription(RuntimeStatus $status, ?NetworkInfo $network, ?string $error): string
    {
        if ( ! $status->reachable) {
            return __('console.network_not_checked');
        }

        if (null !== $error) {
            return $error;
        }

        if ( ! $network instanceof NetworkInfo) {
            return __('console.network_unavailable');
        }

        return __('console.network_available', [
            'driver' => $network->driver ?? __('console.unknown'),
        ]);
    }
}
