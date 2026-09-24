<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\UpdateStorefrontImageAction;
use Misaf\VendraStore\Models\StorefrontImage;

final class ActivateStorefrontImageTableAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'activateStorefrontImage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.activate'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->visible(fn (StorefrontImage $record): bool => ! $record->active)
            ->action(function (StorefrontImage $record, UpdateStorefrontImageAction $updateImage): void {
                $updateImage->execute($record, ['active' => true]);

                Notification::make()->success()->title(__('vendra-console::messages.activated'))->send();
            });
    }
}
