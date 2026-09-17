<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\CancelSubscriptionAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Subscription;

final class CancelSubscriptionTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'cancelSubscription';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.cancel_subscription'))->icon(Heroicon::OutlinedXCircle)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->color('danger')->requiresConfirmation()
            ->visible(fn (Reseller $record): bool => in_array(self::displayedLatestSubscription($record)?->status, [
                SubscriptionStatus::PendingPayment, SubscriptionStatus::Active, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = $record->latestSubscription();
                if (! $subscription instanceof Subscription) {
                    self::notifyUnavailable();

                    return;
                }

                resolve(CancelSubscriptionAction::class)->execute($subscription);
                self::notifySuccess(__('vendra-console::messages.subscription_cancelled'));
            });
    }
}
