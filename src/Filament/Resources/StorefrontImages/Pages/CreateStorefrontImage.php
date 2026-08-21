<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages;

use Filament\Resources\Pages\CreateRecord;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\StorefrontImageResource;

final class CreateStorefrontImage extends CreateRecord
{
    protected static string $resource = StorefrontImageResource::class;
}
