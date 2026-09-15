<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\StorefrontImageResource;
use Misaf\VendraStore\Actions\DeleteStorefrontImageAction;
use Misaf\VendraStore\Actions\UpdateStorefrontImageAction;
use Misaf\VendraStore\Models\StorefrontImage;

final class EditStorefrontImage extends EditRecord
{
    protected static string $resource = StorefrontImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (StorefrontImage $record): bool => $record->isInUse())
                ->using(function (StorefrontImage $record, DeleteStorefrontImageAction $deleteImage): bool {
                    $deleteImage->execute($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array{image?: string, notes?: string|null, active?: bool}  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        throw_unless($record instanceof StorefrontImage, InvalidArgumentException::class, 'Storefront image pages require a StorefrontImage record.');

        return resolve(UpdateStorefrontImageAction::class)->execute($record, $data);
    }
}
