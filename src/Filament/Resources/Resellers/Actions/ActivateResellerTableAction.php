<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use LogicException;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\SetResellerActiveAction;
use Misaf\VendraReseller\Models\Reseller;

final class ActivateResellerTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'activateReseller';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.activate'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->visible(fn (Reseller $record): bool => ! $record->trashed() && ! $record->active)
            ->action(function (Reseller $record, SetResellerActiveAction $setActive): void {
                try {
                    $setActive->execute($record, true);
                } catch (LogicException) {
                    self::notifyUnavailable();

                    return;
                }

                self::notifySuccess(__('vendra-console::messages.activated'));
            });
    }
}
