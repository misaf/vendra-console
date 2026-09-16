<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AssignResellerPageAction;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;

final class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AssignResellerPageAction::make(),
            ViewAction::make(),
        ];
    }
}
