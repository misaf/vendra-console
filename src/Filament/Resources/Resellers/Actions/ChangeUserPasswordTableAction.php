<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\UpdateResellerUserPasswordAction;
use Misaf\VendraReseller\Models\Reseller;

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
            ->label(__('console.change_user_password'))->icon(Heroicon::OutlinedKey)
            ->disabled(fn (Reseller $record): bool => $record->user() === null)
            ->tooltip(fn (Reseller $record): ?string => $record->user() === null ? __('console.user_account_required') : null)
            ->schema([
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(UpdateResellerUserPasswordAction::class)->execute(
                    $record->user() ?? throw new InvalidArgumentException('Reseller user account is required.'),
                    (string) Arr::get($data, 'password'),
                );
                self::notifySuccess(__('console.user_password_updated'));
            });
    }
}
