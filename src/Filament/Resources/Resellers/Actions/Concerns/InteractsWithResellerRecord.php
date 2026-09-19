<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;

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

    protected static function notifySuccess(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }

    protected static function notifyUnavailable(): void
    {
        Notification::make()->danger()->title(__('vendra-console::messages.record_unavailable'))->send();
    }
}
