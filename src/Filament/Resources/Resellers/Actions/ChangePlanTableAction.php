<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\ChangeSubscriptionPlanAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;
use Misaf\VendraSubscription\Exceptions\SubscriptionPaymentException;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Support\MoneyFormatter;
use Misaf\VendraSubscription\Support\PlanChangeQuote;

final class ChangePlanTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'changePlan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_plan'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->hidden(fn (Reseller $record): bool => $record->trashed())->slideOver()
            ->schema([Select::make('plan_id')->label(__('vendra-console::navigation.plan'))
                ->options(fn (Reseller $record): array => self::planOptions($record->activeSubscription()))->required()->native(false)])
            ->action(function (Reseller $record, array $data): void {
                $plan = Plan::query()->findOrFail(Arr::integer($data, 'plan_id'));

                if (! self::walletCoversChange($record, $plan)) {
                    return;
                }

                try {
                    $subscription = resolve(ChangeSubscriptionPlanAction::class)->execute($record, $plan);
                } catch (SubscriptionLimitException|SubscriptionPaymentException $exception) {
                    Notification::make()->danger()->title(__('vendra-console::messages.downgrade_blocked'))->body($exception->getMessage())->send();

                    return;
                }

                self::notifySuccess(self::outcome($subscription, $plan));
            });
    }

    /**
     * Label each plan with what choosing it does: a prorated charge now, a
     * switch at the period end, or dropping a scheduled change.
     *
     * @return array<int, string>
     */
    private static function planOptions(?Subscription $current): array
    {
        return Plan::query()
            ->active()
            ->orderBy('price')
            ->get()
            ->mapWithKeys(fn (Plan $plan): array => [$plan->id => self::describe($plan, $current)])
            ->all();
    }

    private static function describe(Plan $plan, ?Subscription $current): string
    {
        $label = "{$plan->name} · {$plan->formattedPrice()}";

        if ($current?->plan_id === $plan->id) {
            return $label.' · '.__('vendra-console::messages.plan_change_current');
        }

        $quote = PlanChangeQuote::for($current, $plan);

        if (! $quote->appliesNow) {
            return $label.' · '.__('vendra-console::messages.plan_change_from', ['date' => $current?->ends_at?->format('Y-m-d')]);
        }

        if ($quote->isProrated()) {
            return $label.' · '.__('vendra-console::messages.plan_change_prorated', ['amount' => MoneyFormatter::format($quote->amount, $plan->currency_code)]);
        }

        return $label;
    }

    private static function walletCoversChange(Reseller $reseller, Plan $plan): bool
    {
        $current = $reseller->activeSubscription();

        if ($current?->plan_id === $plan->id) {
            return true;
        }

        $quote = PlanChangeQuote::for($current, $plan);

        return ! $quote->appliesNow || self::walletCovers($reseller, $quote->amount ?? $plan->price, $plan->currency_code);
    }

    private static function outcome(Subscription $subscription, Plan $plan): string
    {
        if ($subscription->scheduled_plan_id === $plan->id) {
            return __('vendra-console::messages.plan_change_scheduled', ['plan' => $plan->name]);
        }

        if ($subscription->status === SubscriptionStatus::PendingPayment) {
            return __('vendra-console::messages.plan_change_pending_payment', ['plan' => $plan->name]);
        }

        return __('vendra-console::messages.plan_changed');
    }
}
