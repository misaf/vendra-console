<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Actions;

use Filament\Actions\DeleteAction;
use Misaf\VendraConsole\Filament\Resources\Plans\Actions\Concerns\DeletesPlan;

final class DeletePlanTableAction extends DeleteAction
{
    use DeletesPlan;
}
