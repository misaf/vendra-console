<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\Concerns;

use Misaf\VendraStore\Actions\DeleteStorefrontImageAction;
use Misaf\VendraStore\Models\StorefrontImage;

trait DeletesStorefrontImage
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hidden(fn (StorefrontImage $record): bool => $record->isInUse())
            ->using(function (StorefrontImage $record, DeleteStorefrontImageAction $deleteImage): bool {
                $deleteImage->execute($record);

                return true;
            });
    }
}
