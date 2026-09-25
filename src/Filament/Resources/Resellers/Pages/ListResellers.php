<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\Resellers\ResellerResource;
use Misaf\VendraConsole\Filament\Resources\Resellers\Widgets\ResellerSubscriptionOverview;

final class ListResellers extends ListRecords
{
    protected static string $resource = ResellerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ResellerSubscriptionOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
