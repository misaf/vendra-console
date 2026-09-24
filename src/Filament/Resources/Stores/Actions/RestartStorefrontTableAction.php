<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\RestartStoreStorefrontAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class RestartStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'restartStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.restart_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => $record->storefrontDeployment instanceof StorefrontDeployment && ! $record->keepsStorefrontDown())
            ->action(function (Store $record, RestartStoreStorefrontAction $restartStorefront): void {
                $deployment = $record->storefrontDeployment;

                if (! $deployment instanceof StorefrontDeployment) {
                    self::notifyUnavailable();

                    return;
                }

                self::run(
                    function () use ($restartStorefront, $deployment): void {
                        $restartStorefront->execute($deployment);
                    },
                    __('vendra-console::messages.storefront_restarted'),
                );
            });
    }
}
