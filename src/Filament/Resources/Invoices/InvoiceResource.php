<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Invoices;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\Invoices\Pages\ListInvoices;
use Misaf\VendraConsole\Filament\Resources\Invoices\Tables\InvoiceTable;
use Misaf\VendraSubscription\Models\SubscriptionInvoice;

final class InvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'invoices';

    public static function getModelLabel(): string
    {
        return __('vendra-console::navigation.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-console::navigation.invoices');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-console::navigation.invoices');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group');
    }

    public static function getNavigationSort(): int
    {
        return 7;
    }

    public static function table(Table $table): Table
    {
        return InvoiceTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
