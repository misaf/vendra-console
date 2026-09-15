<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\StorefrontImageResource;
use Misaf\VendraStore\Actions\CreateStorefrontImageAction;

final class CreateStorefrontImage extends CreateRecord
{
    protected static string $resource = StorefrontImageResource::class;

    /**
     * @param  array{image: string, notes?: string|null, active?: bool}  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return resolve(CreateStorefrontImageAction::class)->execute($data);
    }
}
