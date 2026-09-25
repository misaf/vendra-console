<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Currencies\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Misaf\VendraConsole\Filament\Resources\Currencies\CurrencyResource;

final class ViewCurrency extends ViewRecord
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
