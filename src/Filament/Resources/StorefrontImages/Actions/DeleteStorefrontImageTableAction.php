<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions;

use Filament\Actions\DeleteAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\Concerns\DeletesStorefrontImage;

final class DeleteStorefrontImageTableAction extends DeleteAction
{
    use DeletesStorefrontImage;
}
