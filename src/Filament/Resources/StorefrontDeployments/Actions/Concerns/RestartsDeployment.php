<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\RestartStoreStorefrontAction;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait RestartsDeployment
{
    use InteractsWithDeploymentRecord;

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
            ->action(fn (
                StorefrontDeployment $record,
                RestartStoreStorefrontAction $restart,
            ): mixed => self::run(
                fn (): mixed => $restart->execute($record),
                __('vendra-console::messages.storefront_restarted'),
            ));
    }
}
