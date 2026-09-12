<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ReconcileDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RestartDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RetryDeploymentTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ViewLogsTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;

final class ViewStorefrontDeployment extends ViewRecord
{
    protected static string $resource = StorefrontDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewLogsTableAction::make(),
            RetryDeploymentTableAction::make(),
            ReconcileDeploymentTableAction::make(),
            RestartDeploymentTableAction::make(),
        ];
    }
}
