<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\ReactivateSubscriptionAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Subscription;

final class ReactivateSubscriptionTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'reactivateSubscription';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.reactivate_subscription'))->icon(Heroicon::OutlinedPlayCircle)
            ->visible(fn (Reseller $record): bool => in_array(self::latestSubscription($record)?->status, [
                SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    resolve(ReactivateSubscriptionAction::class)->execute($subscription);
                    self::notifySuccess(__('console.subscription_reactivated'));
                }
            });
    }
}
