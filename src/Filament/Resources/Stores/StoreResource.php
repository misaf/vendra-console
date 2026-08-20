<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\CreateStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\EditStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\DomainsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Schemas\StoreForm;
use Misaf\VendraConsole\Filament\Resources\Stores\Tables\StoreTable;
use Misaf\VendraStore\Models\Store;

final class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'stores';

    public static function getModelLabel(): string
    {
        return __('console.store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.stores');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.stores');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreTable::configure($table);
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
        $store = self::store($record);
        $domainName = $store->domains->pluck('name')->first();

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
            'index'  => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit'   => EditStore::route('/{record}/edit'),
        ];
    }

    private static function store(Model $record): Store
    {
        if ( ! $record instanceof Store) {
            throw new InvalidArgumentException('Store resources require a Store record.');
        }

        return $record;
    }
}
