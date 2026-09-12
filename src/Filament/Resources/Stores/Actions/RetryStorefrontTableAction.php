<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\RetryFailedStorefrontDeploymentAction;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class RetryStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'retryStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.retry_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Store $record): bool => self::deployment($record)?->status === StorefrontDeploymentStatus::Failed)
            ->action(function (Store $record, RetryFailedStorefrontDeploymentAction $retryStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    $retryStorefront->execute($deployment);
                    self::notify(__('console.storefront_retry_queued'));
                }
            });
    }
}
