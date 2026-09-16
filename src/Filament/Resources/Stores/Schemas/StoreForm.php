<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component as Livewire;
use Misaf\LaravelEmailVerification\Rules\EmailValidation;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Forms\Components\StoreDomainInput;
use Misaf\VendraStore\Filament\Resources\Stores\Schemas\StoreForm as BaseStoreForm;

final class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return BaseStoreForm::configure($schema, creationFields: self::storeFields(), storefrontIsOptional: true);
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
                ->options(fn (): array => Reseller::query()->active()->pluck('name', 'id')->all())
                ->placeholder(__('vendra-console::attributes.platform_owned_store'))
                ->searchable()
                ->preload()
                ->native(false)
                ->visibleOn('create'),

            StoreDomainInput::make(),

            TextInput::make('email')
                ->afterStateUpdated(function (?string $state, Get $get, Set $set, Livewire $livewire): void {
                    $livewire->validateOnly('data.email');

                    if (filled($state) && blank($get('storefront_contact_email'))) {
                        $set('storefront_contact_email', $state);
                    }
                })
                ->label(__('vendra-console::attributes.email'))
                ->email()
                ->autocomplete('email')
                ->placeholder('admin@example.com')
                ->extraAttributes(['dir' => 'ltr'])
                ->live(onBlur: true)
                ->maxLength(255)
                ->required()
                ->rules([
                    'bail',
                    'email:rfc,strict,spoof,filter,filter_unicode',
                    new EmailValidation,
                ])
                ->visibleOn('create'),
        ];
    }
}
