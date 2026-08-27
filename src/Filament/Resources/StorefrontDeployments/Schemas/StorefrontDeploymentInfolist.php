<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontObservation;
use Misaf\VendraStore\Support\StorefrontReference;
use Throwable;

final class StorefrontDeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('console.deployment_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('store.name')
                                    ->label(__('console.store')),
                                TextEntry::make('status')
                                    ->label(__('console.status'))
                                    ->badge()
                                    ->formatStateUsing(fn(StorefrontDeploymentStatus $state): string => __("console.deployment_status_{$state->value}")),
                                TextEntry::make('desired_state')
                                    ->label(__('console.desired_state'))
                                    ->badge()
                                    ->formatStateUsing(fn(StorefrontDesiredState $state): string => __("console.desired_state_{$state->value}")),
                                TextEntry::make('slug')
                                    ->label(__('console.storefront_slug'))
                                    ->copyable(),
                                TextEntry::make('domain')
                                    ->label(__('console.domain'))
                                    ->copyable(),
                                TextEntry::make('theme')
                                    ->label(__('console.storefront_theme')),
                                TextEntry::make('image')
                                    ->label(__('console.storefront_image_reference'))
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('image_digest')
                                    ->label(__('console.image_digest'))
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('container_name')
                                    ->label(__('console.container_name'))
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('requested_at')
                                    ->label(__('console.requested_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('deployed_at')
                                    ->label(__('console.deployed_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('failed_at')
                                    ->label(__('console.failed_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('error')
                                    ->label(__('console.failure_information'))
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('console.runtime_observation'))
                    ->description(__('console.runtime_observation_description'))
                    ->schema([
                        TextEntry::make('runtime_observation')
                            ->hiddenLabel()
                            ->state(fn(StorefrontDeployment $record, StorefrontProvisioner $provisioner): array => self::runtimeObservation($record, $provisioner))
                            ->listWithLineBreaks()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /** @return list<string> */
    private static function runtimeObservation(
        StorefrontDeployment $deployment,
        StorefrontProvisioner $provisioner,
    ): array {
        try {
            $observation = $provisioner->observe(StorefrontReference::for($deployment));

            return self::observationLines($observation);
        } catch (Throwable $exception) {
            report($exception);

            return [__('console.runtime_unavailable_message', ['message' => $exception->getMessage()])];
        }
    }

    /** @return list<string> */
    private static function observationLines(StorefrontObservation $observation): array
    {
        return [
            __('console.runtime_state_value', ['state' => __("console.runtime_state_{$observation->state->value}")]),
            __('console.container_name_value', ['name' => $observation->containerName ?? '—']),
            __('console.image_value', ['image' => $observation->image ?? '—']),
            __('console.domain_value', ['domain' => $observation->domain ?? '—']),
        ];
    }
}
