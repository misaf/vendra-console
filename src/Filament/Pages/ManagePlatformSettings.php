<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Settings\BillingSettings;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraStore\Settings\StoreCreationSettings;

/**
 * Edit `StoreCreationSettings`, `ConsoleSettings` and `BillingSettings` in one transaction.
 *
 * `ConsoleSettings` and `BillingSettings` are saved from
 * `mutateFormDataBeforeSave()`, which runs inside the transaction the console
 * panel's `databaseTransactions()` opens around `save()`, so a failed store
 * creation save rolls them back too.
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
                        TextInput::make('brand_name')
                            ->label(__('vendra-console::attributes.brand_name'))
                            ->helperText(__('vendra-console::attributes.brand_name_hint'))
                            ->required()
                            ->string()
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),
                Section::make(__('vendra-console::attributes.billing'))
                    ->description(__('vendra-console::attributes.billing_description'))
                    ->schema([
                        TextInput::make('seller_name')
                            ->label(__('vendra-console::attributes.seller_name'))
                            ->helperText(__('vendra-console::attributes.seller_name_hint'))
                            ->string()
                            ->maxLength(255),
                        Textarea::make('seller_address')
                            ->label(__('vendra-console::attributes.seller_address'))
                            ->rows(3)
                            ->maxLength(1000),
                        TextInput::make('seller_tax_id')
                            ->label(__('vendra-console::attributes.seller_tax_id'))
                            ->string()
                            ->maxLength(64),
                        TextInput::make('tax_rate_percentage')
                            ->label(__('vendra-console::attributes.tax_rate'))
                            ->helperText(__('vendra-console::attributes.tax_rate_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('tax_label')
                            ->label(__('vendra-console::attributes.tax_label'))
                            ->required()
                            ->string()
                            ->maxLength(32),
                    ])
                    ->columns(2)
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
            'brand_name' => resolve(ConsoleSettings::class)->brand_name,
            ...self::billingFormData(resolve(BillingSettings::class)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        resolve(ConsoleSettings::class)
            ->fill(['brand_name' => mb_trim(Arr::string($data, 'brand_name'))])
            ->save();

        resolve(BillingSettings::class)
            ->fill([
                'seller_name' => self::nullableTrimmed($data, 'seller_name'),
                'seller_address' => self::nullableTrimmed($data, 'seller_address'),
                'seller_tax_id' => self::nullableTrimmed($data, 'seller_tax_id'),
                'tax_rate' => self::basisPoints(Arr::get($data, 'tax_rate_percentage', null)),
                'tax_label' => mb_trim(Arr::string($data, 'tax_label')),
            ])
            ->save();

        foreach (['brand_name', 'seller_name', 'seller_address', 'seller_tax_id', 'tax_rate_percentage', 'tax_label'] as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function billingFormData(BillingSettings $settings): array
    {
        return [
            'seller_name' => $settings->seller_name,
            'seller_address' => $settings->seller_address,
            'seller_tax_id' => $settings->seller_tax_id,
            'tax_rate_percentage' => $settings->tax_rate / 100,
            'tax_label' => $settings->tax_label,
        ];
    }

    /**
     * Store the percentage the form shows, such as 19.5, as basis points (1950).
     */
    private static function basisPoints(mixed $percentage): int
    {
        return is_numeric($percentage) ? (int) round((float) $percentage * 100) : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableTrimmed(array $data, string $key): ?string
    {
        $value = is_string($data[$key] ?? null) ? mb_trim($data[$key]) : '';

        return $value === '' ? null : $value;
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
            resolve(BillingSettings::class)->refresh();
        }
    }

    public function getTitle(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }
}
