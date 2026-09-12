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
use Misaf\VendraReseller\Actions\ReplaceResellerUserAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

final class ReplaceUserAccountTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'replaceUserAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.replace_user_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Reseller $record): bool => self::latestUser($record) instanceof User)
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('console.username'))->minLength(3)->maxLength(12)
                    ->rules(['alpha_dash'])->required()->rule(Rule::unique(User::class, 'username')->withoutTrashed()),
                TextInput::make('email')->label(__('console.email'))->email()->required()
                    ->rule(Rule::unique(User::class, 'email')->withoutTrashed()),
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(ReplaceResellerUserAction::class)->execute(
                    $record,
                    (string) Arr::get($data, 'username'),
                    (string) Arr::get($data, 'email'),
                    (string) Arr::get($data, 'password'),
                );
                self::notifySuccess(__('console.user_account_replaced'));
            });
    }
}
