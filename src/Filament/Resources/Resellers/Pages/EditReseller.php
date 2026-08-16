<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Actions\CreateResellerOwnerAction;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Models\ResellerUser;
use Misaf\VendraSubscription\Actions\SubscribeAction;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;
use Misaf\VendraSubscription\Models\Plan;

final class EditReseller extends EditRecord
{
    protected static string $resource = ResellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->createOwnerAccountAction(),
            $this->changeOwnerPasswordAction(),
            $this->changePlanAction(),
            $this->renewAction(),
            OffboardResellerAction::make(),
        ];
    }

    private function createOwnerAccountAction(): Action
    {
        return Action::make('createOwnerAccount')
            ->label(__('console.create_owner_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn(): bool => ! $this->resellerHasOwner())
            ->schema([
                TextInput::make('username')
                    ->label(__('console.username'))
                    ->minLength(3)
                    ->maxLength(12)
                    ->rules(['alpha_dash'])
                    ->required()
                    ->rule(Rule::unique(ResellerUser::class, 'username')->withoutTrashed()),

                TextInput::make('email')
                    ->label(__('console.email'))
                    ->email()
                    ->maxLength(255)
                    ->default(fn(): ?string => $this->getRecord() instanceof Reseller
                        ? $this->getRecord()->email
                        : null)
                    ->required()
                    ->rule(Rule::unique(ResellerUser::class, 'email')->withoutTrashed()),

                TextInput::make('password')
                    ->label(__('console.new_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->confirmed()
                    ->rule(Password::default()),

                TextInput::make('password_confirmation')
                    ->label(__('console.confirm_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $username = $data['username'] ?? null;
                $email = $data['email'] ?? null;
                $password = $data['password'] ?? null;

                if ( ! $record instanceof Reseller
                    || ! is_string($username)
                    || ! is_string($email)
                    || ! is_string($password)) {
                    return;
                }

                app(CreateResellerOwnerAction::class)->execute($record, $username, $email, $password);

                Notification::make()
                    ->success()
                    ->title(__('console.owner_account_created'))
                    ->send();
            });
    }

    private function changeOwnerPasswordAction(): Action
    {
        return Action::make('changeOwnerPassword')
            ->label(__('console.change_owner_password'))
            ->icon(Heroicon::OutlinedKey)
            ->disabled(fn(): bool => ! $this->resellerHasOwner())
            ->tooltip(fn() => $this->resellerHasOwner()
                ? null
                : __('console.owner_account_required'))
            ->schema([
                TextInput::make('password')
                    ->label(__('console.new_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->confirmed()
                    ->rule(Password::default()),

                TextInput::make('password_confirmation')
                    ->label(__('console.confirm_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $password = $data['password'] ?? null;

                if ( ! $record instanceof Reseller || ! is_string($password)) {
                    return;
                }

                $record->ownerUser()->firstOrFail()->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                Notification::make()
                    ->success()
                    ->title(__('console.owner_password_updated'))
                    ->send();
            });
    }

    private function resellerHasOwner(): bool
    {
        $record = $this->getRecord();

        return $record instanceof Reseller && $record->ownerUser()->exists();
    }

    private function changePlanAction(): Action
    {
        return Action::make('changePlan')
            ->label(__('console.change_plan'))
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Select::make('plan_id')
                    ->label(__('console.plan'))
                    ->options(fn(): array => Plan::query()->active()->pluck('name', 'id')->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $planId = $data['plan_id'] ?? null;

                if ( ! $record instanceof Reseller || ! is_numeric($planId)) {
                    return;
                }

                $plan = Plan::query()->findOrFail((int) $planId);

                try {
                    app(SubscribeAction::class)->execute($record, $plan);
                } catch (SubscriptionLimitException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('console.downgrade_blocked'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('console.plan_changed'))
                    ->send();
            });
    }

    private function renewAction(): Action
    {
        return Action::make('renew')
            ->label(__('console.renew'))
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getRecord();

                if ( ! $record instanceof Reseller) {
                    return;
                }

                $subscription = $record->activeSubscription()
                    ?? $record->subscriptions()->latest('starts_at')->first();
                $plan = $subscription?->plan;

                if (null === $plan) {
                    Notification::make()
                        ->danger()
                        ->title(__('console.no_active_subscription'))
                        ->send();

                    return;
                }

                app(SubscribeAction::class)->execute($record, $plan);

                Notification::make()
                    ->success()
                    ->title(__('console.subscription_renewed'))
                    ->send();
            });
    }
}
