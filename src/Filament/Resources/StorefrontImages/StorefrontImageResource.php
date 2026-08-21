<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\CreateStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\EditStorefrontImage;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Pages\ListStorefrontImages;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Schemas\StorefrontImageForm;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Tables\StorefrontImagesTable;
use Misaf\VendraStore\Models\StorefrontImage;

final class StorefrontImageResource extends Resource
{
    protected static ?string $model = StorefrontImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'storefront-images';

    public static function getModelLabel(): string
    {
        return __('console.storefront_image');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.storefront_images');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.storefront_images');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return StorefrontImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorefrontImagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStorefrontImages::route('/'),
            'create' => CreateStorefrontImage::route('/create'),
            'edit'   => EditStorefrontImage::route('/{record}/edit'),
        ];
    }
}
