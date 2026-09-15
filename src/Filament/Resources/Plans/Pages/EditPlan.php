<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;
use Misaf\VendraSubscription\Actions\DeletePlanAction;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Models\Plan;

final class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Plan $record): bool => $record->isInUse())
                ->using(function (Plan $record, DeletePlanAction $deletePlan): bool {
                    $deletePlan->execute($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        throw_unless($record instanceof Plan, InvalidArgumentException::class, 'Plan pages require a Plan record.');

        return resolve(UpdatePlanAction::class)->execute($record, $data);
    }
}
