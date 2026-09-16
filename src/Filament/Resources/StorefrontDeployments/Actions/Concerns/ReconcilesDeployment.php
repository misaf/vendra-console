<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\ReconcileStoreStorefrontAction;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait ReconcilesDeployment
{
    use InteractsWithDeploymentRecord;

    public static function getDefaultName(): string
    {
        return 'reconcileDeployment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.reconcile_storefront'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->requiresConfirmation()
            ->action(fn (
                StorefrontDeployment $record,
                ReconcileStoreStorefrontAction $reconcile,
            ): mixed => self::run(
                fn (): mixed => $reconcile->execute($record),
                __('vendra-console::messages.storefront_reconciled'),
            ));
    }
}
