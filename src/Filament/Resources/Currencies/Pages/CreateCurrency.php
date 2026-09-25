<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Currencies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Misaf\VendraConsole\Filament\Resources\Currencies\CurrencyResource;

final class CreateCurrency extends CreateRecord
{
    protected static string $resource = CurrencyResource::class;
}
