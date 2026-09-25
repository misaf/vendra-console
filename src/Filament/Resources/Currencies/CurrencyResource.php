<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Currencies;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\Currencies\Pages\CreateCurrency;
use Misaf\VendraConsole\Filament\Resources\Currencies\Pages\EditCurrency;
use Misaf\VendraConsole\Filament\Resources\Currencies\Pages\ListCurrencies;
use Misaf\VendraConsole\Filament\Resources\Currencies\Pages\ViewCurrency;
use Misaf\VendraCurrency\Filament\Clusters\Resources\Currencies\Schemas\CurrencyForm;
use Misaf\VendraCurrency\Filament\Clusters\Resources\Currencies\Schemas\CurrencyInfolist;
use Misaf\VendraCurrency\Filament\Clusters\Resources\Currencies\Tables\CurrencyTable;
use Misaf\VendraCurrency\Models\Currency;
use Misaf\VendraSupport\Tenancy\TenantAwareness;

/**
 * The platform's currencies, which plans are priced and reseller wallets are
 * credited in. They are the tenantless rows; each store keeps its own.
 */
final class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $slug = 'currencies';

    public static function getModelLabel(): string
    {
        return __('vendra-currency::navigation.currency');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-currency::navigation.currencies');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-currency::navigation.currencies');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group_resellers');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function form(Schema $schema): Schema
    {
        return CurrencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CurrencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrencyTable::configure($table, self::class);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'symbol'];
    }

    public static function getEloquentQuery(): Builder
    {
        return TenantAwareness::constrainToCurrentTenant(parent::getEloquentQuery());
    }

    /**
     * Console access is the panel gate; `Currency`'s policy checks store permissions.
     */
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
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function canReorder(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'view' => ViewCurrency::route('/{record}'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
