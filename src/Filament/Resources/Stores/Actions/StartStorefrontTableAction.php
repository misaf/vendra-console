<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\StartStoreStorefrontAction;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class StartStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'startStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.start_storefront'))
            ->icon(Heroicon::OutlinedPlay)
            ->visible(fn (Store $record): bool => $record->storefrontDeployment?->desired_state === StorefrontDesiredState::Stopped && ! $record->keepsStorefrontDown())
            ->action(function (Store $record, StartStoreStorefrontAction $startStorefront): void {
                $deployment = $record->storefrontDeployment;

                if (! $deployment instanceof StorefrontDeployment) {
                    self::notifyUnavailable();

                    return;
                }

                self::run(
                    function () use ($startStorefront, $deployment): void {
                        $startStorefront->execute($deployment);
                    },
                    __('vendra-console::messages.storefront_started'),
                );
            });
    }
}
