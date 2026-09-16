<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\AssignsReseller;

final class AssignResellerPageAction extends Action
{
    use AssignsReseller;
}
