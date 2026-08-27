<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\ActivityLogs\Pages;

use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\ActivityLogResource;

final class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /**
     * The audit trail is a record of what happened; nothing here creates one.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
