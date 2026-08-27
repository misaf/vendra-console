<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\StorefrontDeploymentActions;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;

final class ViewStorefrontDeployment extends ViewRecord
{
    protected static string $resource = StorefrontDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StorefrontDeploymentActions::logs(),
            StorefrontDeploymentActions::retry(),
            StorefrontDeploymentActions::reconcile(),
            StorefrontDeploymentActions::restart(),
        ];
    }
}
