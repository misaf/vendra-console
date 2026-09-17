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
            ->label(__('vendra-console::actions.redeploy_storefront'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => $record->storefrontDeployment instanceof StorefrontDeployment && ! $record->keepsStorefrontDown())
            ->action(function (Store $record, RedeployStoreStorefrontAction $redeployStorefront): void {
                $deployment = $record->storefrontDeployment;

                if (! $deployment instanceof StorefrontDeployment) {
                    self::notifyUnavailable();

                    return;
                }

                self::run(
                    fn (): mixed => $redeployStorefront->execute($deployment),
                    __('vendra-console::messages.storefront_redeployment_queued'),
                );
            });
    }
}
