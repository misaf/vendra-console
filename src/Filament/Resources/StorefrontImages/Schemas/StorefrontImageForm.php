<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Component as Livewire;
use Misaf\VendraSupport\Filament\Forms\Components\IsActiveToggle;

final class StorefrontImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('image')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.image'))
                    ->label(__('vendra-console::attributes.storefront_image_reference'))
                    ->helperText(__('vendra-console::attributes.storefront_image_reference_hint'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label(__('vendra-console::attributes.storefront_image_notes'))
                    ->autosize()
                    ->rows(2)
                    ->columnSpanFull(),

                IsActiveToggle::make()
                    ->default(true),
            ]);
    }
}
