<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Enums\StoreStatus;
use Misaf\VendraStore\Filament\Widgets\StoreStatusOverview as BaseStoreStatusOverview;
use Misaf\VendraStore\Models\Store;

final class StoreStatusOverview extends BaseStoreStatusOverview
{
    protected function stores(): Builder
    {
        return Store::query();
    }

    protected function statusUrl(StoreStatus $status): string
    {
        return StoreResource::getUrl('index', ['filters' => ['status' => ['values' => [$status->value]]]]);
    }

    protected function failedStorefrontsUrl(): string
    {
        return StorefrontDeploymentResource::getUrl('index', ['filters' => ['status' => ['value' => StorefrontDeploymentStatus::Failed->value]]]);
    }
}
