<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
            ->label(__('console.create_user_account'))->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Reseller $record): bool => self::latestUser($record) === null)
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('console.username'))->minLength(3)->maxLength(12)
                    ->rules(['alpha_dash'])->required()->rule(Rule::unique(User::class, 'username')->withoutTrashed()),
                TextInput::make('email')->label(__('console.email'))->email()->maxLength(255)
                    ->default(fn (Reseller $record): ?string => $record->email)->required()
                    ->rule(Rule::unique(User::class, 'email')->withoutTrashed()),
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(CreateResellerUserAction::class)->execute(
                    $record,
                    (string) Arr::get($data, 'username'),
                    (string) Arr::get($data, 'email'),
                    (string) Arr::get($data, 'password'),
                );
                self::notifySuccess(__('console.user_account_created'));
            });
    }
}
