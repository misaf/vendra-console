<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;

final class ChangeUserPasswordTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'changeUserPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_user_password'))->icon(Heroicon::OutlinedKey)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->schema([
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(UpdateUserPasswordAction::class)->execute(
                    $record->user,
                    Arr::string($data, 'password'),
                );
                self::notifySuccess(__('vendra-console::messages.user_password_updated'));
            });
    }
}
