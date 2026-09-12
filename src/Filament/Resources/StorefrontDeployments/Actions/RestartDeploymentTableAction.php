<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\InteractsWithDeploymentRecord;
use Misaf\VendraStore\Actions\RestartStoreStorefrontAction;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class RestartDeploymentTableAction extends Action
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
            ->label(__('console.restart_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn (
                StorefrontDeployment $record,
                RestartStoreStorefrontAction $restart,
            ): mixed => self::run(
                fn (): mixed => $restart->execute($record),
                __('console.storefront_restarted'),
            ));
    }
}
