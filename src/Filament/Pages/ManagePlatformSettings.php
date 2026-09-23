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
 * Edit `StoreCreationSettings` and `ConsoleSettings` in one transaction.
 *
 * `ConsoleSettings` is saved from `mutateFormDataBeforeSave()`, which runs
 * inside the transaction the console panel's `databaseTransactions()` opens
 * around `save()`, so a failed store creation save rolls it back too.
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
            ->fill(['platform_name' => mb_trim(Arr::string($data, 'platform_name'))])
            ->save();

        unset($data['platform_name']);

        return $data;
    }

    /**
     * Reload the shared console settings after every save.
     *
     * A rolled-back save would otherwise leave the unsaved name on the instance
     * the panel brand reads for the rest of the request.
     */
    public function save(): void
    {
        try {
            parent::save();
        } finally {
            resolve(ConsoleSettings::class)->refresh();
        }
    }

    public function getTitle(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }
}
