<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Pages;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Pages\CreateStorePage;
use Misaf\VendraSubscription\Contracts\SubscriptionSubscriber;

final class CreateStore extends CreateStorePage
{
    protected static string $resource = StoreResource::class;

    /**
     * Use the reseller picked on the form; none means a platform store.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveReseller(array $data): ?SubscriptionSubscriber
    {
        $resellerId = Arr::get($data, 'reseller_id', null);

        if ($resellerId === null || $resellerId === '') {
            return null;
        }

        throw_unless(is_numeric($resellerId), InvalidArgumentException::class, 'Invalid reseller provided.');

        return Reseller::query()->findOrFail((int) $resellerId);
    }
}
