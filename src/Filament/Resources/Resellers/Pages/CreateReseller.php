<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
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
        $planId = Arr::get($data, 'plan_id', null);
        $email = Arr::get($data, 'email', null);
        $username = Arr::get($data, 'username', null);
        $password = Arr::get($data, 'password', null);
        $active = Arr::get($data, 'active', true);

        throw_unless(is_numeric($planId), InvalidArgumentException::class, 'Invalid plan provided.');

        throw_if(! is_string($email) || ! is_string($username) || ! is_string($password), InvalidArgumentException::class, 'Invalid reseller owner credentials provided.');

        $plan = Plan::query()->findOrFail((int) $planId);

        return Arr::get(resolve(CreateResellerAction::class)->execute(
            plan: $plan,
            username: $username,
            email: $email,
            password: $password,
            active: is_bool($active) ? $active : true,
        ), 'reseller');
    }
}
