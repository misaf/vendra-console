<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Concerns\InteractsWithStorefrontRuntime;
use Misaf\VendraStore\Actions\RequestStorefrontReconciliationAction;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait ReconcilesDeployment
{
    use InteractsWithStorefrontRuntime;

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
            ->action(function (
                StorefrontDeployment $record,
                RequestStorefrontReconciliationAction $requestReconciliation,
            ): void {
                self::run(
                    fn (): mixed => $requestReconciliation->execute($record),
                    __('vendra-console::messages.storefront_reconciled'),
                );
            });
    }
}
