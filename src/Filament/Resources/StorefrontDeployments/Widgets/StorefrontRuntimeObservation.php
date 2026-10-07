<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Misaf\VendraStore\Enums\StorefrontRuntimeState;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontObservation;
use Misaf\VendraStore\Support\StorefrontRuntimeSnapshots;
use Throwable;

final class StorefrontRuntimeObservation extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '5s';

    protected function getHeading(): string
    {
        return __('vendra-console::attributes.runtime_observation');
    }

    protected function getDescription(): string
    {
        return __('vendra-console::attributes.runtime_observation_description');
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        try {
            $snapshot = resolve(StorefrontRuntimeSnapshots::class)->latest($this->deployment());
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make(__('vendra-console::attributes.status'), __('vendra-console::attributes.runtime_state_unknown'))
                    ->description(__('vendra-console::messages.runtime_unavailable_message', ['message' => $exception->getMessage()]))
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('danger'),
            ];
        }

        if ($snapshot?->observation === null) {
            return [
                Stat::make(__('vendra-console::attributes.status'), __('vendra-console::attributes.runtime_state_unknown'))
                    ->description($snapshot?->error === null
                        ? __('vendra-console::messages.runtime_read_pending')
                        : __('vendra-console::messages.runtime_unavailable_message', ['message' => $snapshot->error]))
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color($snapshot?->error === null ? 'gray' : 'danger'),
            ];
        }

        return self::observationStats($snapshot->observation, $snapshot->checkedAt->diffForHumans());
    }

    /**
     * @return list<Stat>
     */
    private static function observationStats(StorefrontObservation $observation, string $checkedAt): array
    {
        return [
            Stat::make(__('vendra-console::attributes.status'), __("vendra-console::attributes.runtime_state_{$observation->state->value}"))
                ->description(__('vendra-console::messages.runtime_checked_at', ['time' => $checkedAt]))
                ->icon(Heroicon::OutlinedServerStack)
                ->color(match ($observation->state) {
                    StorefrontRuntimeState::Running => 'success',
                    StorefrontRuntimeState::Created, StorefrontRuntimeState::Stopped => 'warning',
                    StorefrontRuntimeState::Unhealthy, StorefrontRuntimeState::Unknown => 'danger',
                    StorefrontRuntimeState::Absent => 'gray',
                }),
            Stat::make(__('vendra-console::attributes.container_name'), $observation->containerName ?? '—')
                ->icon(Heroicon::OutlinedCube),
            Stat::make(__('vendra-console::attributes.domain'), $observation->domain ?? '—')
                ->icon(Heroicon::OutlinedGlobeAlt),
            Stat::make(__('vendra-console::attributes.storefront_image_reference'), $observation->image ?? '—')
                ->icon(Heroicon::OutlinedPhoto),
        ];
    }

    private function deployment(): StorefrontDeployment
    {
        throw_unless($this->record instanceof StorefrontDeployment, LogicException::class, 'The runtime observation requires a storefront deployment record.');

        return $this->record;
    }
}
