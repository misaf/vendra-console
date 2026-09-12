<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\SuspendStoreAction;
use Misaf\VendraStore\Models\Store;

final class SuspendStoreTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'suspendStore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.suspend_store'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => ! $record->trashed() && $record->active)
            ->action(function (Store $record, SuspendStoreAction $suspendStore): void {
                $suspendStore->execute($record);
                self::notify(__('console.store_suspended'));
            });
    }
}
