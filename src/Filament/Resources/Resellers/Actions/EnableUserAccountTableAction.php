<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\SetResellerUserAccountEnabledAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

final class EnableUserAccountTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'enableUserAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.enable_user_account'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Reseller $record): bool => self::currentUser($record) === null && self::latestUser($record) instanceof User)
            ->action(function (Reseller $record): void {
                $user = self::latestUser($record);
                if ($user instanceof User) {
                    resolve(SetResellerUserAccountEnabledAction::class)->execute($record, $user, true);
                    self::notifySuccess(__('console.user_account_enabled'));
                }
            });
    }
}
