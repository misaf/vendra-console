<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\CreateResellerUserAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

final class CreateUserAccountTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'createUserAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.create_user_account'))->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Reseller $record): bool => $record->latestUser() === null)
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('vendra-console::attributes.username'))->minLength(3)->maxLength(12)
                    ->rules(['alpha_dash'])->required()->rule(Rule::unique(User::class, 'username')->withoutTrashed()),
                TextInput::make('email')->label(__('vendra-console::attributes.email'))->email()->maxLength(255)
                    ->default(fn (Reseller $record): ?string => $record->email)->required()
                    ->rule(Rule::unique(User::class, 'email')->withoutTrashed()),
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(CreateResellerUserAction::class)->execute(
                    $record,
                    (string) Arr::get($data, 'username'),
                    (string) Arr::get($data, 'email'),
                    (string) Arr::get($data, 'password'),
                );
                self::notifySuccess(__('vendra-console::messages.user_account_created'));
            });
    }
}
