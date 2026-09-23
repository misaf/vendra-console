<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraStore\Actions\DeleteStorefrontImageAction;
use Misaf\VendraStore\Exceptions\StorefrontImageInUseException;
use Misaf\VendraStore\Models\StorefrontImage;

trait DeletesStorefrontImage
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hidden(fn (StorefrontImage $record): bool => self::isInUse($record))
            ->using(function (StorefrontImage $record, DeleteStorefrontImageAction $deleteImage): bool {
                try {
                    $deleteImage->execute($record);
                } catch (StorefrontImageInUseException $exception) {
                    Notification::make()->danger()->title(__('vendra-console::messages.delete_blocked'))->body($exception->getMessage())->send();

                    return false;
                }

                return true;
            });
    }

    /**
     * Read the flag the resource query preloads, so a table does not query each row.
     */
    private static function isInUse(StorefrontImage $record): bool
    {
        return $record->hasAttribute('in_use') ? (bool) $record->getAttribute('in_use') : $record->isInUse();
    }
}
