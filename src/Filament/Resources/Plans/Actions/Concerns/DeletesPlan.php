<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Exceptions\PlanInUseException;
use Misaf\VendraSubscription\Models\Plan;

trait DeletesPlan
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hidden(fn (Plan $record): bool => $record->trashed() || self::isInUse($record))
            ->using(function (Plan $record, DeletePlanAction $deletePlan): bool {
                try {
                    $deletePlan->execute($record);
                } catch (PlanInUseException $exception) {
                    Notification::make()->danger()->title(__('vendra-console::messages.delete_blocked'))->body($exception->getMessage())->send();

                    return false;
                }

                return true;
            });
    }

    /**
     * Read the flag the resource query preloads, so a table does not query each row.
     */
    private static function isInUse(Plan $record): bool
    {
        return $record->hasAttribute('in_use') ? (bool) $record->getAttribute('in_use') : $record->isInUse();
    }
}
