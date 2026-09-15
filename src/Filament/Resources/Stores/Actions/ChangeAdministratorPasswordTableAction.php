<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
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
                TextInput::make('password')
                    ->label(__('vendra-console::attributes.new_password'))
                    ->password()
                    ->required()
                    ->confirmed()
                    ->rule(Password::default()),
                TextInput::make('password_confirmation')
                    ->label(__('vendra-console::attributes.confirm_password'))
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (User $record, array $data, UpdateUserPasswordAction $updatePassword): void {
                $updatePassword->execute($record, (string) Arr::get($data, 'password'));
                self::notifySuccess(__('vendra-console::messages.administrator_password_updated'));
            });
    }
}
