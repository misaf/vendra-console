<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Pages;

use Filament\Resources\Pages\CreateRecord;
use Misaf\VendraConsole\Filament\Resources\Plans\PlanResource;

final class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;
}
