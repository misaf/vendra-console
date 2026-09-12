<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\RestoreOffboardedStoreAction;
use Misaf\VendraStore\Models\Store;

final class RestoreStoreTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'restoreOffboardedStore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.restore_store'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (Store $record): bool => $record->trashed())
            ->action(function (Store $record, RestoreOffboardedStoreAction $restoreOffboardedStore): void {
                $restoreOffboardedStore->execute($record);
                self::notify(__('console.store_restored'));
            });
    }
}
