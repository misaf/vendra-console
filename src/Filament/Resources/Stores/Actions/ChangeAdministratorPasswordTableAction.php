<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;

final class ChangeAdministratorPasswordTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'changeAdministratorPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_administrator_password'))
            ->schema([
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (User $record, array $data, UpdateUserPasswordAction $updatePassword): void {
                $updatePassword->execute($record, (string) Arr::get($data, 'password'));
                self::notifySuccess(__('vendra-console::messages.administrator_password_updated'));
            });
    }
}
