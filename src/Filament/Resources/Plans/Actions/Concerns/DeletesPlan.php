<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions\Concerns;

use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Models\Plan;

trait DeletesPlan
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hidden(fn (Plan $record): bool => $record->trashed() || $record->isInUse())
            ->using(function (Plan $record, DeletePlanAction $deletePlan): bool {
                $deletePlan->execute($record);

                return true;
            });
    }
}
