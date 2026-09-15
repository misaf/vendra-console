<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Settings\StoreCreationSettings;

final class ManagePlatformSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = StoreCreationSettings::class;

    protected static ?string $slug = 'platform-settings';

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group');
    }

    public static function getNavigationSort(): int
    {
        return 7;
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('vendra-console::attributes.store_creation'))
                    ->description(__('vendra-console::attributes.store_creation_description'))
                    ->schema([
                        Toggle::make('open')
                            ->label(__('vendra-console::attributes.store_creation_open'))
                            ->helperText(__('vendra-console::attributes.store_creation_open_hint'))
                            ->rules(['boolean']),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function getTitle(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }
}
