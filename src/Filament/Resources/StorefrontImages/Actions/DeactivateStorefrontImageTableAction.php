<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\UpdateStorefrontImageAction;
use Misaf\VendraStore\Models\StorefrontImage;

final class DeactivateStorefrontImageTableAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'deactivateStorefrontImage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.deactivate'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (StorefrontImage $record): bool => $record->active)
            ->action(function (StorefrontImage $record, UpdateStorefrontImageAction $updateImage): void {
                $updateImage->execute($record, ['active' => false]);

                Notification::make()->success()->title(__('vendra-console::messages.deactivated'))->send();
            });
    }
}
