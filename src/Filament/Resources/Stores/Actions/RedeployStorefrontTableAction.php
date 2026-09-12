<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\RedeployStoreStorefrontAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class RedeployStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'redeployStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.redeploy_storefront'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => self::deployment($record) instanceof StorefrontDeployment)
            ->action(function (Store $record, RedeployStoreStorefrontAction $redeployStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    $redeployStorefront->execute($deployment);
                    self::notify(__('console.storefront_redeployment_queued'));
                }
            });
    }
}
