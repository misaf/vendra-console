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
            ->label(__('console.cancel_subscription'))->icon(Heroicon::OutlinedXCircle)
            ->color('danger')->requiresConfirmation()
            ->visible(fn (Reseller $record): bool => in_array(self::latestSubscription($record)?->status, [
                SubscriptionStatus::PendingPayment, SubscriptionStatus::Active, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    resolve(CancelSubscriptionAction::class)->execute($subscription);
                    self::notifySuccess(__('console.subscription_cancelled'));
                }
            });
    }
}
