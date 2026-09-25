<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\RenewSubscriptionAction;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;
use Misaf\VendraSubscription\Exceptions\SubscriptionPaymentException;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Support\PlanCoverage;

final class RenewSubscriptionTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'renew';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.renew'))->icon(Heroicon::OutlinedArrowPath)
            ->hidden(fn (Reseller $record): bool => $record->trashed() || ! $record->renewableSubscription() instanceof Subscription)
            ->requiresConfirmation()
            ->modalDescription(fn (Reseller $record): ?string => self::describe($record->renewableSubscription()))
            ->action(function (Reseller $record): void {
                $subscription = $record->renewableSubscription();

                if (! $subscription instanceof Subscription) {
                    Notification::make()->danger()->title(__('vendra-console::attributes.no_active_subscription'))->send();

                    return;
                }

                $plan = self::renewalPlan($subscription);

                if ($plan instanceof Plan && ! self::walletCovers($record, $plan->price, $plan->currency_code)) {
                    return;
                }

                try {
                    resolve(RenewSubscriptionAction::class)->execute($subscription);
                } catch (SubscriptionLimitException|SubscriptionPaymentException $exception) {
                    Notification::make()->danger()->title(__('vendra-console::messages.renewal_blocked'))->body($exception->getMessage())->send();

                    return;
                }

                self::notifySuccess(__('vendra-console::messages.subscription_renewed'));
            });
    }

    private static function renewalPlan(?Subscription $subscription): ?Plan
    {
        return $subscription instanceof Subscription ? resolve(PlanCoverage::class)->renewalPlan($subscription) : null;
    }

    private static function describe(?Subscription $subscription): ?string
    {
        $price = self::renewalPlan($subscription)?->formattedPrice();

        if (! $subscription instanceof Subscription || ! resolve(PlanCoverage::class)->scheduledPlanOutgrown($subscription)) {
            return $price;
        }

        return implode(' ', array_filter([$price, __('vendra-console::messages.scheduled_plan_outgrown', [
            'plan' => $subscription->scheduledPlan?->name,
            'current' => $subscription->plan?->name,
        ])]));
    }
}
