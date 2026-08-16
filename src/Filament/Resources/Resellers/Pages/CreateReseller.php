<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
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
        $planId = $data['plan_id'] ?? null;
        $email = $data['email'] ?? null;
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $active = $data['active'] ?? true;

        if ( ! is_numeric($planId)) {
            throw new InvalidArgumentException('Invalid plan provided.');
        }

        if ( ! is_string($email) || ! is_string($username) || ! is_string($password)) {
            throw new InvalidArgumentException('Invalid reseller owner credentials provided.');
        }

        $plan = Plan::query()->findOrFail((int) $planId);

        return app(CreateResellerAction::class)->execute(
            plan: $plan,
            username: $username,
            email: $email,
            password: $password,
            active: is_bool($active) ? $active : true,
        )['reseller'];
    }
}
