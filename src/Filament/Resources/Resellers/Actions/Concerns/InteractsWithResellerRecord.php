<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraUser\Models\User;

trait InteractsWithResellerRecord
{
    protected static function currentUser(Reseller $reseller): ?User
    {
        return $reseller->user();
    }

    protected static function latestUser(Reseller $reseller): ?User
    {
        return $reseller->latestUser();
    }

    protected static function latestSubscription(Reseller $reseller): ?Subscription
    {
        return $reseller->latestSubscription();
    }

    protected static function notifySuccess(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
