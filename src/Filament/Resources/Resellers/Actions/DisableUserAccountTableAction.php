<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\SetResellerUserAccountEnabledAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

final class DisableUserAccountTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'disableUserAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.disable_user_account'))
            ->icon(Heroicon::OutlinedNoSymbol)->color('warning')->requiresConfirmation()
            ->visible(fn (Reseller $record): bool => self::currentUser($record) instanceof User)
            ->action(function (Reseller $record): void {
                $user = self::currentUser($record);
                if ($user instanceof User) {
                    resolve(SetResellerUserAccountEnabledAction::class)->execute($record, $user, false);
                    self::notifySuccess(__('console.user_account_disabled'));
                }
            });
    }
}
