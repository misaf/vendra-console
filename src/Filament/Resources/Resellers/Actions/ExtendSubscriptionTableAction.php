<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Actions\ExtendSubscriptionAction;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Subscription;

final class ExtendSubscriptionTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'extendSubscription';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.extend_subscription'))->icon(Heroicon::OutlinedCalendarDays)
            ->visible(fn (Reseller $record): bool => self::latestSubscription($record)?->status === SubscriptionStatus::Active
                && self::latestSubscription($record)?->ends_at !== null)
            ->schema([DateTimePicker::make('ends_at')->label(__('console.ends_at'))
                ->after(fn (Reseller $record): ?Carbon => self::latestSubscription($record)?->ends_at)->required()])
            ->action(function (Reseller $record, array $data): void {
                $subscription = self::latestSubscription($record);
                if ($subscription instanceof Subscription) {
                    resolve(ExtendSubscriptionAction::class)->execute($subscription, Date::parse((string) Arr::get($data, 'ends_at')));
                    self::notifySuccess(__('console.subscription_extended'));
                }
            });
    }
}
