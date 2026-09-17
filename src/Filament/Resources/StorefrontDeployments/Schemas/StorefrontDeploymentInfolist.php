<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontObservation;
use Misaf\VendraStore\Support\StorefrontReference;
use Misaf\VendraSupport\Filament\Infolists\Components\SlugEntry;
use Throwable;

final class StorefrontDeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('vendra-console::attributes.deployment_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('store.name')
                                    ->label(__('vendra-console::navigation.store')),
                                TextEntry::make('status')
                                    ->label(__('vendra-console::attributes.status'))
                                    ->badge(),
                                TextEntry::make('desired_state')
                                    ->label(__('vendra-console::attributes.desired_state'))
                                    ->badge(),
                                SlugEntry::make()
                                    ->label(__('vendra-console::attributes.storefront_slug'))
                                    ->copyable(),
                                TextEntry::make('domain')
                                    ->label(__('vendra-console::attributes.domain'))
                                    ->copyable(),
                                TextEntry::make('image')
                                    ->label(__('vendra-console::attributes.storefront_image_reference'))
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('image_digest')
                                    ->label(__('vendra-console::attributes.image_digest'))
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('container_name')
                                    ->label(__('vendra-console::attributes.container_name'))
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('requested_at')
                                    ->label(__('vendra-console::attributes.requested_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('deployed_at')
                                    ->label(__('vendra-console::attributes.deployed_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('failed_at')
                                    ->label(__('vendra-console::attributes.failed_at'))
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('error')
                                    ->label(__('vendra-console::attributes.failure_information'))
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('vendra-console::attributes.runtime_observation'))
                    ->description(__('vendra-console::attributes.runtime_observation_description'))
                    ->schema([
                        TextEntry::make('runtime_observation')
                            ->hiddenLabel()
                            ->state(fn (StorefrontDeployment $record, StorefrontProvisioner $provisioner): array => self::runtimeObservation($record, $provisioner))
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

            return [__('vendra-console::messages.runtime_unavailable_message', ['message' => $exception->getMessage()])];
        }
    }

    /** @return list<string> */
    private static function observationLines(StorefrontObservation $observation): array
    {
        return [
            __('vendra-console::attributes.runtime_state_value', ['state' => __("vendra-console::attributes.runtime_state_{$observation->state->value}")]),
            __('vendra-console::attributes.container_name_value', ['name' => $observation->containerName ?? '—']),
            __('vendra-console::attributes.image_value', ['image' => $observation->image ?? '—']),
            __('vendra-console::attributes.domain_value', ['domain' => $observation->domain ?? '—']),
        ];
    }
}
