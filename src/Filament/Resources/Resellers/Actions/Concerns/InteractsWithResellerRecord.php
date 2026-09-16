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

    protected static function notifyUnavailable(): void
    {
        Notification::make()->danger()->title(__('vendra-console::messages.record_unavailable'))->send();
    }
}
