<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraStore\Settings\StoreCreationSettings;

/**
 * Edits the platform rules a console user can change at runtime.
 *
 * The page saves `StoreCreationSettings`; the console's own presentation lives
 * in `ConsoleSettings` and is filled and saved alongside it in one transaction.
 */
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
                Section::make(__('vendra-console::attributes.platform'))
                    ->description(__('vendra-console::attributes.platform_description'))
                    ->schema([
                        TextInput::make('platform_name')
                            ->label(__('vendra-console::attributes.platform_name'))
                            ->helperText(__('vendra-console::attributes.platform_name_hint'))
                            ->required()
                            ->string()
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'platform_name' => resolve(ConsoleSettings::class)->platform_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        resolve(ConsoleSettings::class)
            ->fill(['platform_name' => Arr::get($data, 'platform_name')])
            ->save();

        unset($data['platform_name']);

        return $data;
    }

    public function getTitle(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }
}
