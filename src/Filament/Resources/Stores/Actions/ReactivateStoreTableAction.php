<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\ReactivateStoreAction;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraTenant\Enums\TenantProvisioningStatus;

final class ReactivateStoreTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'reactivateStore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.reactivate_store'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->visible(fn (Store $record): bool => ! $record->trashed()
                && ! $record->active
                && $record->provisioning_status === TenantProvisioningStatus::Ready)
            ->action(function (Store $record, ReactivateStoreAction $reactivateStore): void {
                $reactivateStore->execute($record);
                self::notify(__('console.store_reactivated'));
            });
    }
}
