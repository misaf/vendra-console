<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component as Livewire;

final class StorefrontImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.name'))
                    ->label(__('console.name'))
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required(),

                TextInput::make('image')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.image'))
                    ->label(__('console.storefront_image_reference'))
                    ->helperText(__('console.storefront_image_reference_hint'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->required(),

                TagsInput::make('themes')
                    ->label(__('console.storefront_themes'))
                    ->helperText(__('console.storefront_themes_hint'))
                    ->default(['default'])
                    ->nestedRecursiveRules(['required', 'string', 'distinct'])
                    ->required(),

                Toggle::make('active')
                    ->label(__('console.active'))
                    ->default(true)
                    ->onIcon(Heroicon::Bolt)
                    ->required(),
            ])
            ->columns(2);
    }
}
