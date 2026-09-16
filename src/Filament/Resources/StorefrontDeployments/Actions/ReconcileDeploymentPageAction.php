<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\ReconcilesDeployment;

final class ReconcileDeploymentPageAction extends Action
{
    use ReconcilesDeployment;
}
