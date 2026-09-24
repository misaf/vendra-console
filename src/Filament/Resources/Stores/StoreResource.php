<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\CreateStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\EditStore;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ListStores;
use Misaf\VendraConsole\Filament\Resources\Stores\Pages\ViewStore;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\AdministratorsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers\DomainsRelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Schemas\StoreForm;
use Misaf\VendraConsole\Filament\Resources\Stores\Schemas\StoreInfolist;
use Misaf\VendraConsole\Filament\Resources\Stores\Tables\StoreTable;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Settings\StoreCreationSettings;

final class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'stores';

    public static function getModelLabel(): string
    {
        return __('vendra-console::navigation.store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-console::navigation.stores');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-console::navigation.stores');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group_stores');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StoreInfolist::configure($schema);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([ViewStore::class, EditStore::class]);
    }

    public static function table(Table $table): Table
    {
        return StoreTable::configure($table);
    }

    /**
     * Include offboarded records, so their view and edit pages resolve.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'storefrontDeployment',
            'primaryDomain',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'domains.name'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $store = self::store($record);

        return [
            __('vendra-console::attributes.domain') => $store->primaryDomain->name ?? '—',
        ];
    }

    /**
     * @return array<Action>
     */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        $store = self::store($record);

        return [
            Action::make('openAdmin')
                ->label(__('vendra-console::attributes.admin_url'))
                ->url(
                    $store->adminUrl(),
                    shouldOpenInNewTab: true,
                ),
        ];
    }

    public static function getRelations(): array
    {
        return [
            AdministratorsRelationManager::class,
            DomainsRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return resolve(StoreCreationSettings::class)->open;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'view' => ViewStore::route('/{record}'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }

    private static function store(Model $record): Store
    {
        throw_unless($record instanceof Store, InvalidArgumentException::class, 'Store resources require a Store record.');

        return $record;
    }
}
