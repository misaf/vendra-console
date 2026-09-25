<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSubscription\Support\MoneyFormatter;
use Misaf\VendraSubscription\Support\TaxedAmount;

trait InteractsWithResellerRecord
{
    /**
     * Get the eager-loaded latest subscription, for visibility and form defaults.
     *
     * Writes re-read through {@see Reseller::latestSubscription()}.
     */
    protected static function displayedLatestSubscription(Reseller $reseller): ?Subscription
    {
        if (! $reseller->relationLoaded('subscriptions')) {
            return $reseller->latestSubscription();
        }

        $subscription = $reseller->subscriptions->sortByDesc('starts_at')->first();

        return $subscription instanceof Subscription ? $subscription : null;
    }

    /**
     * Refuse a charge the wallet cannot cover, rather than starting a period
     * whose payment fails and cancels it. The amount is net; the check covers
     * it with tax added, as the charge will be.
     */
    protected static function walletCovers(Reseller $reseller, int $netAmount, ?string $currencyCode): bool
    {
        $amount = TaxedAmount::withProfileTax($netAmount)->total;

        if ($amount === 0 || $currencyCode === null) {
            return true;
        }

        $balance = $reseller->walletBalance($currencyCode);

        if ($balance >= $amount) {
            return true;
        }

        Notification::make()
            ->danger()
            ->title(__('vendra-console::messages.insufficient_wallet_balance'))
            ->body(__('vendra-console::messages.insufficient_wallet_balance_body', [
                'amount' => MoneyFormatter::format($amount, $currencyCode),
                'balance' => MoneyFormatter::format($balance, $currencyCode),
            ]))
            ->send();

        return false;
    }

    protected static function notifySuccess(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }

    protected static function notifyUnavailable(): void
    {
        Notification::make()->danger()->title(__('vendra-console::messages.record_unavailable'))->send();
    }
}
