<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ListStorefrontDeployments;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Pages\ViewStorefrontDeployment;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Schemas\StorefrontDeploymentInfolist;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Tables\StorefrontDeploymentTable;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class StorefrontDeploymentResource extends Resource
{
    protected static ?string $model = StorefrontDeployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $recordTitleAttribute = 'slug';

    protected static ?string $slug = 'storefront-deployments';

    public static function getModelLabel(): string
    {
        return __('console.storefront_deployment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.storefront_deployments');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.storefront_deployments');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function infolist(Schema $schema): Schema
    {
        return StorefrontDeploymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorefrontDeploymentTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store', 'storefrontImage']);
    }

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['slug', 'domain', 'container_name', 'store.name'];
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
            'index' => ListStorefrontDeployments::route('/'),
            'view'  => ViewStorefrontDeployment::route('/{record}'),
        ];
    }
}
