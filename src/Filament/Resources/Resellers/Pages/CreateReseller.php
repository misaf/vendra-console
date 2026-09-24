<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraReseller\Actions\CreateResellerAction;
use Misaf\VendraSubscription\Models\Plan;

final class CreateReseller extends CreateRecord
{
    protected static string $resource = ResellerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        ['reseller' => $reseller] = resolve(CreateResellerAction::class)->execute(
            plan: Plan::query()->findOrFail(Arr::integer($data, 'plan_id')),
            username: Arr::string($data, 'username'),
            email: Arr::string($data, 'email'),
            password: Arr::string($data, 'password'),
            active: Arr::boolean($data, 'active', true),
        );

        return $reseller;
    }
}
