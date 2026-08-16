<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Properties\Pages\CreateProperty;
use Misaf\VendraConsole\Filament\Resources\Properties\Pages\EditProperty;
use Misaf\VendraConsole\Filament\Resources\Properties\Pages\ListProperties;
use Misaf\VendraConsole\Filament\Resources\Properties\RelationManagers\DomainsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Properties\Schemas\PropertyForm;
use Misaf\VendraConsole\Filament\Resources\Properties\Tables\PropertyTable;
use Misaf\VendraTenant\Models\Tenant;

final class PropertyResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'properties';

    public static function getModelLabel(): string
    {
        return __('console.property');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.properties');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.properties');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertyTable::configure($table);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'domains.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'domains' => fn(Relation $relation): Relation => $relation->where('active', true),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $property = self::property($record);
        $domainName = $property->domains->pluck('name')->first();

        return [
            __('console.domain') => is_string($domainName) ? $domainName : '—',
        ];
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit'   => EditProperty::route('/{record}/edit'),
        ];
    }

    private static function property(Model $record): Tenant
    {
        if ( ! $record instanceof Tenant) {
            throw new InvalidArgumentException('Property resources require a Tenant record.');
        }

        return $record;
    }
}
