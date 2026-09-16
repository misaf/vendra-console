<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\ShowsDeploymentLogs;

final class ViewLogsPageAction extends Action
{
    use ShowsDeploymentLogs;
}
