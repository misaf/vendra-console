<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Concerns\InteractsWithStorefrontRuntime;
use Misaf\VendraStore\Actions\RestartStoreStorefrontAction;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait RestartsDeployment
{
    use InteractsWithStorefrontRuntime;

    public static function getDefaultName(): string
    {
        return 'restartDeployment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.restart_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->visible(fn (StorefrontDeployment $record): bool => $record->storeMayServe())
            ->action(function (
                StorefrontDeployment $record,
                RestartStoreStorefrontAction $restart,
            ): void {
                self::run(
                    function () use ($restart, $record): void {
                        $restart->execute($record);
                    },
                    __('vendra-console::messages.storefront_restarted'),
                );
            });
    }
}
