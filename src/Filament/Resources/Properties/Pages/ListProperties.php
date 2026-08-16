<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\Properties\PropertyResource;

final class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
