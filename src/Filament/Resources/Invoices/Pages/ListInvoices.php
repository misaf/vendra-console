<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\Invoices\InvoiceResource;

final class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
