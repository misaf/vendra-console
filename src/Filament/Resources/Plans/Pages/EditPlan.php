<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Plans\Actions\DeletePlanPageAction;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;
use Misaf\VendraConsole\Filament\Resources\Plans\Schemas\PlanForm;
use Misaf\VendraReseller\Support\ResellersOverPlan;
use Misaf\VendraSubscription\Actions\UpdatePlanAction;
use Misaf\VendraSubscription\Models\Plan;

final class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeletePlanPageAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlanForm::normalizeLimits($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        throw_unless($record instanceof Plan, InvalidArgumentException::class, 'Plan pages require a Plan record.');

        return resolve(UpdatePlanAction::class)->execute($record, $data);
    }

    /**
     * Warn when the saved limits leave resellers on this plan above them.
     *
     * Lowering a plan is allowed on purpose, so the save goes through and the
     * resellers can be found with the reseller table's filter.
     */
    protected function afterSave(): void
    {
        throw_unless($this->record instanceof Plan, InvalidArgumentException::class, 'Plan pages require a Plan record.');

        $overPlan = count(resolve(ResellersOverPlan::class)->ids($this->record));

        if ($overPlan === 0) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(trans_choice('vendra-console::messages.plan_leaves_resellers_over', $overPlan, ['count' => $overPlan]))
            ->body(__('vendra-console::messages.plan_leaves_resellers_over_body'))
            ->persistent()
            ->send();
    }
}
