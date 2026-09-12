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
use Misaf\VendraSubscription\Actions\SubscribeAction;
use Misaf\VendraSubscription\Exceptions\SubscriptionLimitException;
use Misaf\VendraSubscription\Models\Plan;

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
            ->label(__('console.change_plan'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)->slideOver()
            ->schema([Select::make('plan_id')->label(__('console.plan'))
                ->options(fn (): array => Plan::query()->active()->pluck('name', 'id')->all())->required()->native(false)])
            ->action(function (Reseller $record, array $data): void {
                try {
                    resolve(SubscribeAction::class)->execute($record, Plan::query()->findOrFail((int) Arr::get($data, 'plan_id')));
                } catch (SubscriptionLimitException $exception) {
                    Notification::make()->danger()->title(__('console.downgrade_blocked'))->body($exception->getMessage())->send();

                    return;
                }

                self::notifySuccess(__('console.plan_changed'));
            });
    }
}
