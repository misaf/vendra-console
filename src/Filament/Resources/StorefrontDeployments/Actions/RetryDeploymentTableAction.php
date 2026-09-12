<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\InteractsWithDeploymentRecord;
use Misaf\VendraStore\Actions\RetryFailedStorefrontDeploymentAction;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class RetryDeploymentTableAction extends Action
{
    use InteractsWithDeploymentRecord;

    public static function getDefaultName(): string
    {
        return 'retryDeployment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.retry_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (StorefrontDeployment $record): bool => $record->status === StorefrontDeploymentStatus::Failed)
            ->action(fn (
                StorefrontDeployment $record,
                RetryFailedStorefrontDeploymentAction $retry,
            ): mixed => self::run(
                fn (): mixed => $retry->execute($record),
                __('console.storefront_retry_queued'),
            ));
    }
}
