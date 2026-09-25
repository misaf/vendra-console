<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Schemas\StorefrontConfigurationFields;
use Misaf\VendraStore\Models\StoreDomain;
use Misaf\VendraUser\Support\UserRules;

final class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('vendra-console::attributes.name'))
                    ->required()
                    ->maxLength(255)
                    ->visibleOn('edit'),

                Textarea::make('description')
                    ->label(__('vendra-console::attributes.description'))
                    ->rows(4)
                    ->maxLength(2000)
                    ->visibleOn('edit')
                    ->columnSpanFull(),

                ...self::storeFields(),

                StorefrontConfigurationFields::creationToggle(default: true)
                    ->visibleOn('create'),

                Grid::make(2)
                    ->schema(StorefrontConfigurationFields::creationIdentityFields(optional: true))
                    ->visible(fn (Get $get): bool => $get('create_storefront') === true)
                    ->visibleOn('create')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * @return list<Select|TextInput>
     */
    public static function storeFields(): array
    {
        return [
            /*
             | Optional on purpose: the console both creates stores for a
             | reseller and creates stores the platform owns outright. Leaving
             | this empty is the second case, not a mistake.
             */
            Select::make('reseller_id')
                ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.reseller_id'))
                ->label(__('vendra-console::navigation.reseller'))
                ->live()
                ->options(fn (): array => Reseller::displayNames(Reseller::query()->active()))
                ->placeholder(__('vendra-console::attributes.platform_owned_store'))
                ->searchable()
                ->preload()
                ->native(false)
                ->visibleOn('create'),

            TextInput::make('domain')
                ->afterStateUpdated(function (?string $state, Get $get, Set $set, Livewire $livewire): void {
                    $livewire->validateOnly('data.domain');

                    if (blank($state)) {
                        return;
                    }

                    $domain = StoreDomain::normalizeDomain($state);
                    $domainLabel = Str::before($domain, '.');

                    if (blank($get('storefront_slug'))) {
                        $set('storefront_slug', Str::slug($domainLabel));
                    }

                })
                ->helperText(__('vendra-console::attributes.domain_helper_text'))
                ->label(__('vendra-console::attributes.domain'))
                ->placeholder('flowers.example')
                ->extraAttributes(['dir' => 'ltr'])
                ->live(onBlur: true)
                ->maxLength(255)
                ->required()
                ->rules(StoreDomain::activeDomainRules())
                ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                    ? null
                    : StoreDomain::normalizeDomain($state))
                ->visibleOn('create'),

            TextInput::make('email')
                ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.email'))
                ->label(__('vendra-console::attributes.email'))
                ->email()
                ->autocomplete('email')
                ->placeholder('admin@example.com')
                ->extraAttributes(['dir' => 'ltr'])
                ->live(onBlur: true)
                ->maxLength(255)
                ->required()
                ->rules(UserRules::email())
                ->visibleOn('create'),
        ];
    }
}
