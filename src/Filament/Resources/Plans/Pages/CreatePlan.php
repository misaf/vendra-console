<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;
use Misaf\VendraConsole\Filament\Resources\Plans\Schemas\PlanForm;
use Misaf\VendraSubscription\Actions\CreatePlanAction;

final class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlanForm::normalizeLimits($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return resolve(CreatePlanAction::class)->execute($data);
    }
}
