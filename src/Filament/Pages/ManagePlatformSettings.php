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

/**
 * Operator-editable platform settings.
 *
 * Platform settings carry no tenant, so this page works without any tenant
 * context — the console panel runs outside the tenant middleware stack. Only
 * settings that already exist and are acted on belong here.
 */
final class ManagePlatformSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = StoreCreationSettings::class;

    protected static ?string $slug = 'platform-settings';

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.platform_settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('console.store_creation'))
                    ->description(__('console.store_creation_description'))
                    ->schema([
                        Toggle::make('open')
                            ->label(__('console.store_creation_open'))
                            ->helperText(__('console.store_creation_open_hint'))
                            ->rules(['boolean']),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function getTitle(): string
    {
        return __('console.platform_settings');
    }
}
