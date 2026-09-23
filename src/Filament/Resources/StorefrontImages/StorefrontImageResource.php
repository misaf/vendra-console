<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\CreateStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\EditStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\ListStorefrontImages;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Schemas\StorefrontImageForm;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Tables\StorefrontImagesTable;
use Misaf\VendraStore\Models\StorefrontImage;

final class StorefrontImageResource extends Resource
{
    protected static ?string $model = StorefrontImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $recordTitleAttribute = 'image';

    protected static ?string $slug = 'storefront-images';

    public static function getModelLabel(): string
    {
        return __('vendra-console::navigation.storefront_image');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-console::navigation.storefront_images');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-console::navigation.storefront_images');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group_stores');
    }

    public static function getNavigationSort(): int
    {
        return 4;
    }

    public static function form(Schema $schema): Schema
    {
        return StorefrontImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorefrontImagesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withExists('deployments as in_use');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontImages::route('/'),
            'create' => CreateStorefrontImage::route('/create'),
            'edit' => EditStorefrontImage::route('/{record}/edit'),
        ];
    }
}
