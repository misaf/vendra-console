<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Schemas;

use Filament\Forms\Components\Textarea;
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
                TextInput::make('image')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.image'))
                    ->label(__('console.storefront_image_reference'))
                    ->helperText(__('console.storefront_image_reference_hint'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label(__('console.storefront_image_notes'))
                    ->autosize()
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('active')
                    ->label(__('console.active'))
                    ->default(true)
                    ->onIcon(Heroicon::Bolt)
                    ->required(),
            ]);
    }
}
