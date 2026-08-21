<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\StorefrontImageResource;
use Misaf\VendraStore\Models\StorefrontImage;

final class EditStorefrontImage extends EditRecord
{
    protected static string $resource = StorefrontImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn(StorefrontImage $record): bool => $record->isInUse()),
        ];
    }
}
