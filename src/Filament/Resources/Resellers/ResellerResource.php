<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\CreateReseller;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\EditReseller;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\Resellers\Schemas\ResellerForm;
use Misaf\VendraConsole\Filament\Resources\Resellers\Tables\ResellerTable;
use Misaf\VendraReseller\Models\Reseller;

final class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'resellers';

    public static function getModelLabel(): string
    {
        return __('console.reseller');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.resellers');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.resellers');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return ResellerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResellerTable::configure($table);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'email'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $reseller = self::reseller($record);

        return [
            __('console.email') => $reseller->email ?? '—',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListResellers::route('/'),
            'create' => CreateReseller::route('/create'),
            'edit'   => EditReseller::route('/{record}/edit'),
        ];
    }

    private static function reseller(Model $record): Reseller
    {
        if ( ! $record instanceof Reseller) {
            throw new InvalidArgumentException('Reseller resources require a Reseller record.');
        }

        return $record;
    }
}
