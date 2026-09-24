<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ReconcileDeploymentPageAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RestartDeploymentPageAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\RetryDeploymentPageAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\ViewLogsPageAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Widgets\StorefrontRuntimeObservation;

final class ViewStorefrontDeployment extends ViewRecord
{
    protected static string $resource = StorefrontDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewLogsPageAction::make(),
            RetryDeploymentPageAction::make(),
            ReconcileDeploymentPageAction::make(),
            RestartDeploymentPageAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            StorefrontRuntimeObservation::class,
        ];
    }
}
