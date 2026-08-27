<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Actions\CreateResellerOwnerAction;
use Misaf\VendraReseller\Actions\ReplaceResellerOwnerAction;
use Misaf\VendraReseller\Actions\SetResellerOwnerAccountEnabledAction;
use Misaf\VendraReseller\Actions\UpdateResellerOwnerEmailAction;
use Misaf\VendraReseller\Actions\UpdateResellerOwnerPasswordAction;
use Misaf\VendraReseller\Filament\Actions\OffboardResellerAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Models\ResellerUser;
use Misaf\VendraSubscription\Actions\CancelSubscriptionAction;
use Misaf\VendraSubscription\Actions\ExtendSubscriptionAction;
use Misaf\VendraSubscription\Actions\ReactivateSubscriptionAction;
use Misaf\VendraSubscription\Actions\SubscribeAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
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
            $this->changeOwnerEmailAction(),
            $this->disableOwnerAction(),
            $this->enableOwnerAction(),
            $this->replaceOwnerAction(),
            $this->changePlanAction(),
            $this->renewAction(),
            $this->extendSubscriptionAction(),
            $this->cancelSubscriptionAction(),
            $this->reactivateSubscriptionAction(),
            OffboardResellerAction::make(),
        ];
    }

    private function createOwnerAccountAction(): Action
    {
        return Action::make('createOwnerAccount')
            ->label(__('console.create_owner_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn(): bool => null === $this->latestOwner())
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

                app(UpdateResellerOwnerPasswordAction::class)->execute(
                    $record->ownerUser()->firstOrFail(),
                    $password,
                );

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

    private function changeOwnerEmailAction(): Action
    {
        return Action::make('changeOwnerEmail')
            ->label(__('console.change_owner_email'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->disabled(fn(): bool => ! $this->resellerHasOwner())
            ->fillForm(fn(): array => ['email' => $this->currentOwner()?->email])
            ->schema([
                TextInput::make('email')
                    ->label(__('console.email'))
                    ->email()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $owner = $this->currentOwner();

                if ( ! $owner instanceof ResellerUser) {
                    return;
                }

                app(UpdateResellerOwnerEmailAction::class)->execute($owner, (string) $data['email']);

                $this->success(__('console.owner_email_updated'));
            });
    }

    private function disableOwnerAction(): Action
    {
        return Action::make('disableOwnerAccount')
            ->label(__('console.disable_owner_account'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn(): bool => $this->currentOwner() instanceof ResellerUser)
            ->action(function (): void {
                $owner = $this->currentOwner();

                if ($owner instanceof ResellerUser) {
                    app(SetResellerOwnerAccountEnabledAction::class)->execute($owner, false);
                    $this->success(__('console.owner_account_disabled'));
                }
            });
    }

    private function enableOwnerAction(): Action
    {
        return Action::make('enableOwnerAccount')
            ->label(__('console.enable_owner_account'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn(): bool => null === $this->currentOwner() && true === $this->latestOwner()?->trashed())
            ->action(function (): void {
                $owner = $this->latestOwner();

                if ($owner instanceof ResellerUser) {
                    app(SetResellerOwnerAccountEnabledAction::class)->execute($owner, true);
                    $this->success(__('console.owner_account_enabled'));
                }
            });
    }

    private function replaceOwnerAction(): Action
    {
        return Action::make('replaceOwnerAccount')
            ->label(__('console.replace_owner_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn(): bool => $this->latestOwner() instanceof ResellerUser)
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
                    ->required()
                    ->rule(Rule::unique(ResellerUser::class, 'email')->withoutTrashed()),
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
            ->action(function (array $data): void {
                $record = $this->getRecord();

                if ( ! $record instanceof Reseller) {
                    return;
                }

                app(ReplaceResellerOwnerAction::class)->execute(
                    $record,
                    (string) $data['username'],
                    (string) $data['email'],
                    (string) $data['password'],
                );

                $this->success(__('console.owner_account_replaced'));
            });
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

    private function extendSubscriptionAction(): Action
    {
        return Action::make('extendSubscription')
            ->label(__('console.extend_subscription'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->visible(fn(): bool => SubscriptionStatus::Active === $this->latestSubscription()?->status
                && null !== $this->latestSubscription()?->ends_at)
            ->schema([
                DateTimePicker::make('ends_at')
                    ->label(__('console.ends_at'))
                    ->after(fn(): ?Carbon => $this->latestSubscription()?->ends_at)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $subscription = $this->latestSubscription();

                if (null === $subscription) {
                    return;
                }

                app(ExtendSubscriptionAction::class)->execute(
                    $subscription,
                    Carbon::parse((string) $data['ends_at']),
                );

                $this->success(__('console.subscription_extended'));
            });
    }

    private function cancelSubscriptionAction(): Action
    {
        return Action::make('cancelSubscription')
            ->label(__('console.cancel_subscription'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn(): bool => in_array($this->latestSubscription()?->status, [
                SubscriptionStatus::PendingPayment,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
            ], true))
            ->action(function (): void {
                $subscription = $this->latestSubscription();

                if (null !== $subscription) {
                    app(CancelSubscriptionAction::class)->execute($subscription);
                    $this->success(__('console.subscription_cancelled'));
                }
            });
    }

    private function reactivateSubscriptionAction(): Action
    {
        return Action::make('reactivateSubscription')
            ->label(__('console.reactivate_subscription'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->visible(fn(): bool => in_array($this->latestSubscription()?->status, [
                SubscriptionStatus::Cancelled,
                SubscriptionStatus::Expired,
                SubscriptionStatus::PastDue,
            ], true))
            ->action(function (): void {
                $subscription = $this->latestSubscription();

                if (null !== $subscription) {
                    app(ReactivateSubscriptionAction::class)->execute($subscription);
                    $this->success(__('console.subscription_reactivated'));
                }
            });
    }

    private function currentOwner(): ?ResellerUser
    {
        $record = $this->getRecord();

        return $record instanceof Reseller ? $record->ownerUser()->first() : null;
    }

    private function latestOwner(): ?ResellerUser
    {
        $record = $this->getRecord();

        return $record instanceof Reseller
            ? $record->ownerUser()->withTrashed()->latest('id')->first()
            : null;
    }

    private function latestSubscription(): ?\Misaf\VendraSubscription\Models\Subscription
    {
        $record = $this->getRecord();

        return $record instanceof Reseller ? $record->latestSubscription() : null;
    }

    private function success(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
