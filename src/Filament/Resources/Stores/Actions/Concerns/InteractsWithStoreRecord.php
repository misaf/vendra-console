<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraConsole\Filament\Concerns\InteractsWithStorefrontRuntime;

trait InteractsWithStoreRecord
{
    use InteractsWithStorefrontRuntime;

    protected static function notify(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }

    protected static function notifyUnavailable(): void
    {
        Notification::make()->danger()->title(__('vendra-console::messages.record_unavailable'))->send();
    }
}
