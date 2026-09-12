<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\SubscribeAction;

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
            ->label(__('console.renew'))->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(function (Reseller $record): void {
                $plan = ($record->activeSubscription() ?? $record->subscriptions()->latest('starts_at')->first())?->plan;
                if ($plan === null) {
                    Notification::make()->danger()->title(__('console.no_active_subscription'))->send();

                    return;
                }

                resolve(SubscribeAction::class)->execute($record, $plan);
                self::notifySuccess(__('console.subscription_renewed'));
            });
    }
}
