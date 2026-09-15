<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Actions\UpdateUserEmailAction;
use Misaf\VendraUser\Models\User;

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
                    ->rule(fn (User $record, RelationManager $livewire): mixed => Rule::unique(User::class, 'email')
                        ->where(TenantSchema::column(), self::administratorStore($livewire)->id)
                        ->ignore($record->getKey())),
            ])
            ->action(function (User $record, array $data, UpdateUserEmailAction $updateEmail): void {
                $updateEmail->execute($record, (string) Arr::get($data, 'email'));
                self::notifySuccess(__('vendra-console::messages.administrator_email_updated'));
            });
    }
}
