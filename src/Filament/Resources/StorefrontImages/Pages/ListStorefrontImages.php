<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\StorefrontImageResource;

final class ListStorefrontImages extends ListRecords
{
    protected static string $resource = StorefrontImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
