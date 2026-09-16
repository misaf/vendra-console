<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Misaf\VendraStore\Services\StorefrontContainerRuntime;
use Misaf\VendraStore\Support\StorefrontNetwork;
use Misaf\VendraStore\Support\StorefrontRuntimeStatus;
use Misaf\VendraStore\Support\StorefrontSettings;
use Throwable;

/**
 * Every open dashboard polls this widget, so the runtime probe is cached for
 * slightly less than the polling interval: however many tabs are open, the
 * daemon sees at most one ping and one network lookup per window.
 */
final class ContainerRuntimeHealth extends StatsOverviewWidget
{
    private const string CACHE_KEY = 'vendra-console:runtime-health';

    private const int CACHE_SECONDS = 25;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $networkName = resolve(StorefrontSettings::class)->network;

        try {
            ['status' => $status, 'network' => $network, 'error' => $error] = Cache::remember(
                self::CACHE_KEY.':'.$networkName,
                self::CACHE_SECONDS,
                fn (): array => $this->probe($networkName),
            );
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make(__('vendra-console::attributes.container_runtime'), __('vendra-console::attributes.unknown'))
                    ->description($exception->getMessage())
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('danger'),
            ];
        }

        return [
            $this->runtimeStat($status),
            $this->networkStat($networkName, $status, $network, $error),
        ];
    }

    /**
     * @return array{status: StorefrontRuntimeStatus, network: ?StorefrontNetwork, error: ?string}
     */
    private function probe(string $networkName): array
    {
        $runtime = resolve(StorefrontContainerRuntime::class);
        $status = $runtime->status();
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

        return ['status' => $status, 'network' => $network, 'error' => $error];
    }

    private function runtimeStat(StorefrontRuntimeStatus $status): Stat
    {
        $description = match (true) {
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
                ! $status->reachable, $status->engineMismatch() => 'danger',
                default => 'success',
            });
    }

    private function networkStat(string $networkName, StorefrontRuntimeStatus $status, ?StorefrontNetwork $network, ?string $error): Stat
    {
        return Stat::make(__('vendra-console::attributes.storefront_network'), $networkName)
            ->description($this->networkDescription($status, $network, $error))
            ->icon(Heroicon::OutlinedShare)
            ->color($status->reachable && $network instanceof StorefrontNetwork ? 'success' : 'danger');
    }

    private function networkDescription(StorefrontRuntimeStatus $status, ?StorefrontNetwork $network, ?string $error): string
    {
        if (! $status->reachable) {
            return __('vendra-console::messages.network_not_checked');
        }

        if ($error !== null) {
            return $error;
        }

        if (! $network instanceof StorefrontNetwork) {
            return __('vendra-console::messages.network_unavailable');
        }

        return __('vendra-console::messages.network_available', [
            'driver' => $network->driver ?? __('vendra-console::attributes.unknown'),
        ]);
    }
}
