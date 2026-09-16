<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\RetriesDeployment;

final class RetryDeploymentPageAction extends Action
{
    use RetriesDeployment;
}
