<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Unique;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\AddTenantAdministratorAction;
use Misaf\VendraUser\Support\UserRules;

/**
 * A trashed administrator frees its email but keeps its username, matching the
 * users table's unique indexes.
 */
final class AddAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'addAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.add_administrator'))
            ->slideOver()
            ->schema([
                TextInput::make('username')
                    ->label(__('vendra-console::attributes.username'))
                    ->required()
                    ->minLength(UserRules::USERNAME_MIN_LENGTH)
                    ->maxLength(UserRules::USERNAME_MAX_LENGTH)
                    ->rules(UserRules::username())
                    ->rule(fn (RelationManager $livewire): Unique => UserRules::unique('username', self::administratorStore($livewire)->id)),
                TextInput::make('email')
                    ->label(__('vendra-console::attributes.email'))
                    ->required()
                    ->email()
                    ->rule(fn (RelationManager $livewire): Unique => UserRules::unique('email', self::administratorStore($livewire)->id)),
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (array $data, RelationManager $livewire, AddTenantAdministratorAction $addAdministrator): void {
                $addAdministrator->execute(
                    self::administratorStore($livewire),
                    (string) Arr::get($data, 'username'),
                    (string) Arr::get($data, 'email'),
                    (string) Arr::get($data, 'password'),
                );

                self::notifySuccess(__('vendra-console::messages.administrator_added'));
            });
    }
}
