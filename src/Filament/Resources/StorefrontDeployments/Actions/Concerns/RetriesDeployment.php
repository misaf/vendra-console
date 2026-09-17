<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\RetryFailedStorefrontDeploymentAction;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait RetriesDeployment
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
            ->label(__('vendra-console::actions.retry_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (StorefrontDeployment $record): bool => $record->status === StorefrontDeploymentStatus::Failed && $record->storeMayServe())
            ->action(fn (
                StorefrontDeployment $record,
                RetryFailedStorefrontDeploymentAction $retry,
            ): mixed => self::run(
                fn (): mixed => $retry->execute($record),
                __('vendra-console::messages.storefront_retry_queued'),
            ));
    }
}
