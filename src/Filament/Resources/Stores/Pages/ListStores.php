<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;

final class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
