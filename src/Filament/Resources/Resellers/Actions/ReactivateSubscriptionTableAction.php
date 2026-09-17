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
            ->label(__('vendra-console::actions.reactivate_subscription'))->icon(Heroicon::OutlinedPlayCircle)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->visible(fn (Reseller $record): bool => in_array($record->latestSubscription()?->status, [
                SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::PastDue,
            ], true))
            ->action(function (Reseller $record): void {
                $subscription = $record->latestSubscription();
                if (! $subscription instanceof Subscription) {
                    self::notifyUnavailable();

                    return;
                }

                resolve(ReactivateSubscriptionAction::class)->execute($subscription);
                self::notifySuccess(__('vendra-console::messages.subscription_reactivated'));
            });
    }
}
