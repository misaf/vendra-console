<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Actions\AddTenantAdministratorAction;
use Misaf\VendraUser\Models\User;

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
                    ->minLength(3)
                    ->maxLength(12)
                    ->rules(['alpha_dash'])
                    ->rule(fn (RelationManager $livewire): mixed => Rule::unique(User::class, 'username')
                        ->where(TenantSchema::column(), self::administratorStore($livewire)->id)),
                TextInput::make('email')
                    ->label(__('vendra-console::attributes.email'))
                    ->required()
                    ->email()
                    ->rule(fn (RelationManager $livewire): mixed => Rule::unique(User::class, 'email')
                        ->where(TenantSchema::column(), self::administratorStore($livewire)->id)
                        ->withoutTrashed()),
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
