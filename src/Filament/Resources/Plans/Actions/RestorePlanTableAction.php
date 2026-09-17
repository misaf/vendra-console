<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions;

use Filament\Actions\RestoreAction;
use Misaf\VendraSubscription\Actions\RestorePlanAction;
use Misaf\VendraSubscription\Models\Plan;

final class RestorePlanTableAction extends RestoreAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->using(function (Plan $record, RestorePlanAction $restorePlan): bool {
            $restorePlan->execute($record);

            return true;
        });
    }
}
