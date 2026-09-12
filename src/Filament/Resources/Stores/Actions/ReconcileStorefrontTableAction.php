<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\ReconcileStoreStorefrontAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class ReconcileStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'reconcileStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.reconcile_storefront'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => self::deployment($record) instanceof StorefrontDeployment)
            ->action(function (Store $record, ReconcileStoreStorefrontAction $reconcileStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    self::run(
                        fn (): mixed => $reconcileStorefront->execute($deployment),
                        __('console.storefront_reconciled'),
                    );
                }
            });
    }
}
