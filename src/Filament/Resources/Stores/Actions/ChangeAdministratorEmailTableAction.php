<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Unique;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\UpdateUserEmailAction;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

final class ChangeAdministratorEmailTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'changeAdministratorEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_administrator_email'))
            ->fillForm(fn (User $record): array => ['email' => $record->email])
            ->schema([
                TextInput::make('email')
                    ->label(__('vendra-console::attributes.email'))
                    ->email()
                    ->required()
                    ->rule(fn (User $record, RelationManager $livewire): Unique => UserRules::unique('email', self::administratorStore($livewire)->id, $record->id)),
            ])
            ->action(function (User $record, array $data, UpdateUserEmailAction $updateEmail): void {
                $updateEmail->execute($record, Arr::string($data, 'email'));
                self::notifySuccess(__('vendra-console::messages.administrator_email_updated'));
            });
    }
}
