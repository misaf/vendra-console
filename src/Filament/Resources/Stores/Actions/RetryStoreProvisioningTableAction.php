<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\RetryStoreProvisioningAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraTenant\Enums\TenantProvisioningStatus;

final class RetryStoreProvisioningTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'retryStoreProvisioning';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.retry_store_provisioning'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Store $record): bool => ! $record->trashed()
                && $record->provisioning_status !== TenantProvisioningStatus::Ready)
            ->action(function (Store $record, RetryStoreProvisioningAction $retryStoreProvisioning): void {
                $retryStoreProvisioning->execute($record);
                self::notify(__('console.store_provisioning_queued'));
            });
    }
}
