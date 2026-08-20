<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Misaf\LaravelEmailValidation\Rules\EmailValidation;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Schemas\StorefrontConfigurationFields;
use Misaf\VendraStore\Models\StoreDomain;

final class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::storeFields(),

                ...StorefrontConfigurationFields::make(optional: true),

                self::activeField(),
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
                ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.reseller_id'))
                ->label(__('console.reseller'))
                ->live()
                ->options(fn(): array => Reseller::query()->active()->pluck('name', 'id')->all())
                ->placeholder(__('console.platform_owned_store'))
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

                    if (blank($get('storefront_name_en'))) {
                        $set('storefront_name_en', Str::headline($domainLabel));
                    }
                })
                ->helperText(__('console.domain_helper_text'))
                ->label(__('console.domain'))
                ->placeholder('flowers.example')
                ->extraAttributes(['dir' => 'ltr'])
                ->live(onBlur: true)
                ->maxLength(255)
                ->required()
                ->rules(StoreDomain::activeDomainRules())
                ->dehydrateStateUsing(fn(?string $state): ?string => null === $state
                    ? null
                    : StoreDomain::normalizeDomain($state))
                ->visibleOn('create'),

            TextInput::make('email')
                ->afterStateUpdated(function (?string $state, Get $get, Set $set, Livewire $livewire): void {
                    $livewire->validateOnly('data.email');

                    if (filled($state) && blank($get('storefront_contact_email'))) {
                        $set('storefront_contact_email', $state);
                    }
                })
                ->label(__('console.email'))
                ->email()
                ->autocomplete('email')
                ->placeholder('owner@example.com')
                ->extraAttributes(['dir' => 'ltr'])
                ->live(onBlur: true)
                ->maxLength(255)
                ->required()
                ->rules([
                    'bail',
                    'email:rfc,strict,spoof,filter,filter_unicode',
                    new EmailValidation(),
                ])
                ->visibleOn('create'),
        ];
    }

    public static function activeField(): Toggle
    {
        return Toggle::make('active')
            ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.active'))
            ->label(__('console.active'))
            ->columnSpanFull()
            ->default(true)
            ->live()
            ->onIcon(Heroicon::Bolt)
            ->required();
    }
}
