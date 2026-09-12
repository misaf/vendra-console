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
            ->label(__('console.restart_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => self::deployment($record) instanceof StorefrontDeployment)
            ->action(function (Store $record, RestartStoreStorefrontAction $restartStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    self::run(
                        fn (): mixed => $restartStorefront->execute($deployment),
                        __('console.storefront_restarted'),
                    );
                }
            });
    }
}
