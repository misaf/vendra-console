<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\ValidatesResellerUser;
use Misaf\VendraReseller\Actions\ReplaceResellerUserAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Support\UserRules;

final class ReplaceUserAccountTableAction extends Action
{
    use InteractsWithResellerRecord;
    use ValidatesResellerUser;

    public static function getDefaultName(): string
    {
        return 'replaceUserAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.replace_user_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('vendra-console::attributes.username'))->minLength(UserRules::USERNAME_MIN_LENGTH)->maxLength(UserRules::USERNAME_MAX_LENGTH)
                    ->rules(self::resellerUsernameRules())->required(),
                TextInput::make('email')->label(__('vendra-console::attributes.email'))->email()->required()
                    ->rules(self::resellerEmailRules()),
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(ReplaceResellerUserAction::class)->execute(
                    $record,
                    Arr::string($data, 'username'),
                    Arr::string($data, 'email'),
                    Arr::string($data, 'password'),
                );
                self::notifySuccess(__('vendra-console::messages.user_account_replaced'));
            });
    }
}
