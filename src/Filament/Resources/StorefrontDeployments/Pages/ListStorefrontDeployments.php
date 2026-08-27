<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages;

use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;

final class ListStorefrontDeployments extends ListRecords
{
    protected static string $resource = StorefrontDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
