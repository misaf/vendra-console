<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Actions\ResetUserAppAuthenticationAction;

final class ResetUserTwoFactorTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'resetUserTwoFactor';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.reset_user_two_factor'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('vendra-console::messages.reset_user_two_factor_description'))
            ->visible(fn (Reseller $record): bool => ! $record->trashed() && $record->user->hasAppAuthentication())
            ->action(function (Reseller $record, ResetUserAppAuthenticationAction $reset): void {
                $reset->execute($record->user);

                self::notifySuccess(__('vendra-console::messages.user_two_factor_reset'));
            });
    }
}
