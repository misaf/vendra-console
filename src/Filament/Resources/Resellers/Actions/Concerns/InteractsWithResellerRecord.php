<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Filament\Notifications\Notification;

trait InteractsWithResellerRecord
{
    protected static function notifySuccess(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
