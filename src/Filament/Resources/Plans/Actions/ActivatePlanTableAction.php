<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Models\Plan;

final class ActivatePlanTableAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'activatePlan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.activate'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->visible(fn (Plan $record): bool => ! $record->trashed() && ! $record->active)
            ->action(function (Plan $record, UpdatePlanAction $updatePlan): void {
                $updatePlan->execute($record, ['active' => true]);

                Notification::make()->success()->title(__('vendra-console::messages.activated'))->send();
            });
    }
}
