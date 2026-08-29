<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraReseller\Actions\CreateResellerOwnerAction;
use Misaf\VendraReseller\Actions\ReplaceResellerOwnerAction;
use Misaf\VendraReseller\Actions\SetResellerOwnerAccountEnabledAction;
use Misaf\VendraReseller\Actions\UpdateResellerOwnerEmailAction;
use Misaf\VendraReseller\Actions\UpdateResellerOwnerPasswordAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Models\ResellerUser;
use Misaf\VendraSubscription\Actions\CancelSubscriptionAction;
use Misaf\VendraSubscription\Actions\ExtendSubscriptionAction;
use Misaf\VendraSubscription\Actions\ReactivateSubscriptionAction;
use Misaf\VendraSubscription\Actions\SubscribeAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;

final class ResellerOperatorActions
{
    /** @return list<Action> */
    public static function owner(): array
    {
        return [
            self::createOwnerAccount(), self::changeOwnerPassword(), self::changeOwnerEmail(),
            self::disableOwner(), self::enableOwner(), self::replaceOwner(),
        ];
    }

    /** @return list<Action> */
    public static function subscription(): array
    {
        return [
            self::changePlan(), self::renew(), self::extendSubscription(),
            self::cancelSubscription(), self::reactivateSubscription(),
        ];
    }

    private static function createOwnerAccount(): Action
    {
        return Action::make('createOwnerAccount')
            ->label(__('console.create_owner_account'))->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn(Reseller $record): bool => null === self::latestOwner($record))
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('console.username'))->minLength(3)->maxLength(12)
                    ->rules(['alpha_dash'])->required()->rule(Rule::unique(ResellerUser::class, 'username')->withoutTrashed()),
                TextInput::make('email')->label(__('console.email'))->email()->maxLength(255)
                    ->default(fn(Reseller $record): ?string => $record->email)->required()
                    ->rule(Rule::unique(ResellerUser::class, 'email')->withoutTrashed()),
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                app(CreateResellerOwnerAction::class)->execute(
                    $record,
                    (string) $data['username'],
                    (string) $data['email'],
                    (string) $data['password'],
                );
                self::success(__('console.owner_account_created'));
            });
    }

    private static function changeOwnerPassword(): Action
    {
        return Action::make('changeOwnerPassword')
            ->label(__('console.change_owner_password'))->icon(Heroicon::OutlinedKey)
            ->disabled(fn(Reseller $record): bool => ! $record->ownerUser()->exists())
            ->tooltip(fn(Reseller $record): ?string => $record->ownerUser()->exists() ? null : __('console.owner_account_required'))
            ->schema([
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                app(UpdateResellerOwnerPasswordAction::class)->execute(
                    $record->ownerUser()->firstOrFail(),
                    (string) $data['password'],
                );
                self::success(__('console.owner_password_updated'));
            });
    }

    private static function changeOwnerEmail(): Action
    {
        return Action::make('changeOwnerEmail')
            ->label(__('console.change_owner_email'))->icon(Heroicon::OutlinedEnvelope)
            ->disabled(fn(Reseller $record): bool => ! $record->ownerUser()->exists())
            ->tooltip(fn(Reseller $record): ?string => $record->ownerUser()->exists() ? null : __('console.owner_account_required'))
            ->fillForm(fn(Reseller $record): array => ['email' => self::currentOwner($record)?->email])
            ->schema([TextInput::make('email')->label(__('console.email'))->email()->required()])
            ->action(function (Reseller $record, array $data): void {
                $owner = self::currentOwner($record);
                if ($owner instanceof ResellerUser) {
                    app(UpdateResellerOwnerEmailAction::class)->execute($owner, (string) $data['email']);
                    self::success(__('console.owner_email_updated'));
                }
            });
    }

    private static function disableOwner(): Action
    {
        return Action::make('disableOwnerAccount')->label(__('console.disable_owner_account'))
            ->icon(Heroicon::OutlinedNoSymbol)->color('warning')->requiresConfirmation()
            ->visible(fn(Reseller $record): bool => self::currentOwner($record) instanceof ResellerUser)
            ->action(function (Reseller $record): void {
                $owner = self::currentOwner($record);
                if ($owner instanceof ResellerUser) {
                    app(SetResellerOwnerAccountEnabledAction::class)->execute($owner, false);
                    self::success(__('console.owner_account_disabled'));
                }
            });
    }

    private static function enableOwner(): Action
    {
        return Action::make('enableOwnerAccount')->label(__('console.enable_owner_account'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn(Reseller $record): bool => null === self::currentOwner($record) && true === self::latestOwner($record)?->trashed())
            ->action(function (Reseller $record): void {
                $owner = self::latestOwner($record);
                if ($owner instanceof ResellerUser) {
                    app(SetResellerOwnerAccountEnabledAction::class)->execute($owner, true);
                    self::success(__('console.owner_account_enabled'));
                }
            });
    }

    private static function replaceOwner(): Action
    {
        return Action::make('replaceOwnerAccount')->label(__('console.replace_owner_account'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn(Reseller $record): bool => self::latestOwner($record) instanceof ResellerUser)
            ->slideOver()
            ->schema([
                TextInput::make('username')->label(__('console.username'))->minLength(3)->maxLength(12)
                    ->rules(['alpha_dash'])->required()->rule(Rule::unique(ResellerUser::class, 'username')->withoutTrashed()),
                TextInput::make('email')->label(__('console.email'))->email()->required()
                    ->rule(Rule::unique(ResellerUser::class, 'email')->withoutTrashed()),
                TextInput::make('password')->label(__('console.new_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->confirmed()->rule(Password::default()),
                TextInput::make('password_confirmation')->label(__('console.confirm_password'))->password()
                    ->revealable(filament()->arePasswordsRevealable())->required()->dehydrated(false),
            ])
            ->action(function (Reseller $record, array $data): void {
                app(ReplaceResellerOwnerAction::class)->execute(
                    $record,
                    (string) $data['username'],
                    (string) $data['email'],
                    (string) $data['password'],
                );
                self::success(__('console.owner_account_replaced'));
            });
    }

    private static function changePlan(): Action
    {
        return Action::make('changePlan')->label(__('console.change_plan'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)->slideOver()
            ->schema([Select::make('plan_id')->label(__('console.plan'))
                ->options(fn(): array => Plan::query()->active()->pluck('name', 'id')->all())->required()->native(false)])
            ->action(function (Reseller $record, array $data): void {
                try {
                    app(SubscribeAction::class)->execute($record, Plan::query()->findOrFail((int) $data['plan_id']));
                } catch (SubscriptionLimitException $exception) {
                    Notification::make()->danger()->title(__('console.downgrade_blocked'))->body($exception->getMessage())->send();
                    return;
                }
                self::success(__('console.plan_changed'));
            });
    }

    private static function renew(): Action
    {
        return Action::make('renew')->label(__('console.renew'))->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(function (Reseller $record): void {
                $plan = ($record->activeSubscription() ?? $record->subscriptions()->latest('starts_at')->first())?->plan;
                if (null === $plan) {
                    Notification::make()->danger()->title(__('console.no_active_subscription'))->send();
                    return;
                }
                app(SubscribeAction::class)->execute($record, $plan);
                self::success(__('console.subscription_renewed'));
            });
    }

    private static function extendSubscription(): Action
    {
        return Action::make('extendSubscription')->label(__('console.extend_subscription'))->icon(Heroicon::OutlinedCalendarDays)
            ->visible(fn(Reseller $record): bool => SubscriptionStatus::Active === self::latestSubscription($record)?->status
                && null !== self::latestSubscription($record)?->ends_at)
            ->schema([DateTimePicker::make('ends_at')->label(__('console.ends_at'))
                ->after(fn(Reseller $record): ?Carbon => self::latestSubscription($record)?->ends_at)->required()])
            ->action(function (Reseller $record, array $data): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    app(ExtendSubscriptionAction::class)->execute($subscription, Carbon::parse((string) $data['ends_at']));
                    self::success(__('console.subscription_extended'));
                }
            });
    }

    private static function cancelSubscription(): Action
    {
        return Action::make('cancelSubscription')->label(__('console.cancel_subscription'))->icon(Heroicon::OutlinedXCircle)
            ->color('danger')->requiresConfirmation()
            ->visible(fn(Reseller $record): bool => in_array(self::latestSubscription($record)?->status, [
                SubscriptionStatus::PendingPayment, SubscriptionStatus::Active, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    app(CancelSubscriptionAction::class)->execute($subscription);
                    self::success(__('console.subscription_cancelled'));
                }
            });
    }

    private static function reactivateSubscription(): Action
    {
        return Action::make('reactivateSubscription')->label(__('console.reactivate_subscription'))->icon(Heroicon::OutlinedPlayCircle)
            ->visible(fn(Reseller $record): bool => in_array(self::latestSubscription($record)?->status, [
                SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    app(ReactivateSubscriptionAction::class)->execute($subscription);
                    self::success(__('console.subscription_reactivated'));
                }
            });
    }

    private static function currentOwner(Reseller $reseller): ?ResellerUser
    {
        return $reseller->ownerUser()->first();
    }

    private static function latestOwner(Reseller $reseller): ?ResellerUser
    {
        return $reseller->ownerUser()->withTrashed()->latest('id')->first();
    }

    private static function latestSubscription(Reseller $reseller): ?Subscription
    {
        return $reseller->latestSubscription();
    }

    private static function success(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
