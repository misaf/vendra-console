<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Currencies\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\Currencies\CurrencyResource;
use Misaf\VendraCurrency\Filament\Clusters\Resources\Currencies\Actions\InstallCurrenciesTableAction;

final class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallCurrenciesTableAction::make(CurrencyResource::class),

            CreateAction::make(),
        ];
    }
}
