<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Actions\AddTenantAdministratorAction;
use Misaf\VendraUser\Actions\DemoteTenantAdministratorAction;
use Misaf\VendraUser\Actions\PromoteTenantAdministratorAction;
use Misaf\VendraUser\Actions\RemoveTenantAdministratorAction;
use Misaf\VendraUser\Actions\SetUserAccountEnabledAction;
use Misaf\VendraUser\Actions\UpdateUserEmailAction;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Exceptions\LastAdministratorException;
use Misaf\VendraUser\Models\User;

final class AdministratorsRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('console.store_administrators');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->withTrashed())
            ->columns([
                TextColumn::make('username')
                    ->label(__('console.username'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('console.email'))
                    ->searchable(),

                IconColumn::make('administrator')
                    ->label(__('console.administrator'))
                    ->boolean()
                    ->state(fn(User $record): bool => $this->isAdministrator($record)),

                IconColumn::make('enabled')
                    ->label(__('console.enabled'))
                    ->boolean()
                    ->state(fn(User $record): bool => ! $record->trashed()),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([$this->addAdministratorAction()])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        $this->changePasswordAction(),
                        $this->changeEmailAction(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        $this->promoteAction(),
                        $this->demoteAction(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        $this->disableAction(),
                        $this->enableAction(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        $this->removeAction(),
                    ])->dropdown(false),
                ]),
            ]);
    }

    private function addAdministratorAction(): Action
    {
        return Action::make('addAdministrator')
            ->label(__('console.add_administrator'))
            ->slideOver()
            ->schema([
                TextInput::make('username')
                    ->label(__('console.username'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(12)
                    ->rules(['alpha_dash'])
                    ->rule(fn(): mixed => Rule::unique(User::class, 'username')
                        ->where(TenantSchema::column(), $this->store()->getKey())),
                TextInput::make('email')
                    ->label(__('console.email'))
                    ->required()
                    ->email()
                    ->rule(fn(): mixed => Rule::unique(User::class, 'email')
                        ->where(TenantSchema::column(), $this->store()->getKey())
                        ->withoutTrashed()),
                TextInput::make('password')
                    ->label(__('console.new_password'))
                    ->password()
                    ->required()
                    ->confirmed()
                    ->rule(Password::default()),
                TextInput::make('password_confirmation')
                    ->label(__('console.confirm_password'))
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data, AddTenantAdministratorAction $addAdministrator): void {
                $addAdministrator->execute(
                    $this->store(),
                    (string) $data['username'],
                    (string) $data['email'],
                    (string) $data['password'],
                );

                $this->notify(__('console.administrator_added'));
            });
    }

    private function changePasswordAction(): Action
    {
        return Action::make('changeAdministratorPassword')
            ->label(__('console.change_administrator_password'))
            ->schema([
                TextInput::make('password')
                    ->label(__('console.new_password'))
                    ->password()
                    ->required()
                    ->confirmed()
                    ->rule(Password::default()),
                TextInput::make('password_confirmation')
                    ->label(__('console.confirm_password'))
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (User $record, array $data, UpdateUserPasswordAction $updatePassword): void {
                $updatePassword->execute($record, (string) $data['password']);
                $this->notify(__('console.administrator_password_updated'));
            });
    }

    private function changeEmailAction(): Action
    {
        return Action::make('changeAdministratorEmail')
            ->label(__('console.change_administrator_email'))
            ->fillForm(fn(User $record): array => ['email' => $record->email])
            ->schema([
                TextInput::make('email')->label(__('console.email'))->email()->required(),
            ])
            ->action(function (User $record, array $data, UpdateUserEmailAction $updateEmail): void {
                $updateEmail->execute($record, (string) $data['email']);
                $this->notify(__('console.administrator_email_updated'));
            });
    }

    private function promoteAction(): Action
    {
        return Action::make('promoteAdministrator')
            ->label(__('console.promote_administrator'))
            ->visible(fn(User $record): bool => ! $record->trashed() && ! $this->isAdministrator($record))
            ->action(function (User $record, PromoteTenantAdministratorAction $promoteAdministrator): void {
                $promoteAdministrator->execute($this->store(), $record);
                $this->notify(__('console.administrator_promoted'));
            });
    }

    private function demoteAction(): Action
    {
        return Action::make('demoteAdministrator')
            ->label(__('console.demote_administrator'))
            ->visible(fn(User $record): bool => ! $record->trashed() && $this->isAdministrator($record))
            ->action(fn(User $record, DemoteTenantAdministratorAction $demoteAdministrator) => $this->guarded(
                fn() => $demoteAdministrator->execute($this->store(), $record),
                __('console.administrator_demoted'),
            ));
    }

    private function disableAction(): Action
    {
        return Action::make('disableAdministrator')
            ->label(__('console.disable_account'))
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn(User $record): bool => ! $record->trashed())
            ->action(fn(User $record, SetUserAccountEnabledAction $setAccountEnabled) => $this->guarded(
                fn() => $setAccountEnabled->execute($this->store(), $record, false),
                __('console.account_disabled'),
            ));
    }

    private function enableAction(): Action
    {
        return Action::make('enableAdministrator')
            ->label(__('console.enable_account'))
            ->visible(fn(User $record): bool => $record->trashed())
            ->action(function (User $record, SetUserAccountEnabledAction $setAccountEnabled): void {
                $setAccountEnabled->execute($this->store(), $record, true);
                $this->notify(__('console.account_enabled'));
            });
    }

    private function removeAction(): Action
    {
        return Action::make('removeAdministrator')
            ->label(__('console.remove_administrator'))
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn(User $record, RemoveTenantAdministratorAction $removeAdministrator) => $this->guarded(
                fn() => $removeAdministrator->execute($this->store(), $record),
                __('console.administrator_removed'),
            ));
    }

    private function guarded(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (LastAdministratorException $exception) {
            Notification::make()->danger()->title(__('console.last_administrator_required'))->body($exception->getMessage())->send();

            return;
        }

        $this->notify($success);
    }

    private function isAdministrator(User $user): bool
    {
        return $this->store()->execute(fn(): bool => $user->hasRole(config()->string('vendra-permission.admin_role')));
    }

    private function store(): Store
    {
        $store = $this->getOwnerRecord();

        if ( ! $store instanceof Store) {
            throw new LogicException('Administrator membership requires a Store owner record.');
        }

        return $store;
    }

    private function notify(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
