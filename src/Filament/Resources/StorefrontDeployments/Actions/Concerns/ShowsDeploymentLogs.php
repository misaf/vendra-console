<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Misaf\VendraConsole\Filament\Concerns\InteractsWithStorefrontRuntime;

trait ShowsDeploymentLogs
{
    use InteractsWithStorefrontRuntime;

    public static function getDefaultName(): string
    {
        return 'viewLogs';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->showsStorefrontLogs();
    }
}
