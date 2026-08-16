<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties\Pages;

use Filament\Resources\Pages\EditRecord;
use Misaf\VendraConsole\Filament\Resources\Properties\PropertyResource;

final class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;
}
