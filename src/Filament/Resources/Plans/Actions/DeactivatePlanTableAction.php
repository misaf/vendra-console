<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Models\Plan;

final class DeactivatePlanTableAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'deactivatePlan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.deactivate'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Plan $record): bool => ! $record->trashed() && $record->active)
            ->action(function (Plan $record, UpdatePlanAction $updatePlan): void {
                $updatePlan->execute($record, ['active' => false]);

                Notification::make()->success()->title(__('vendra-console::messages.deactivated'))->send();
            });
    }
}
