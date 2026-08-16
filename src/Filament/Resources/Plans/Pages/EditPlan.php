<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;
use Misaf\VendraSubscription\Models\Plan;

final class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn(Plan $record): bool => $record->isInUse()),
        ];
    }
}
