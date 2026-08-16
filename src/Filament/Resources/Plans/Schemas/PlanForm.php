<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Misaf\VendraSubscription\Enums\PeriodUnit;

final class PlanForm
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

                TextInput::make('max_units')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.max_units'))
                    ->label(__('console.max_units'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(1)
                    ->required(),

                Select::make('period_unit')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.period_unit'))
                    ->label(__('console.period_unit'))
                    ->live()
                    ->native(false)
                    ->options(self::periodUnitOptions())
                    ->required(),

                TextInput::make('period_count')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.period_count'))
                    ->label(__('console.period_count'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(1)
                    ->required(),

                TextInput::make('grace_days')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.grace_days'))
                    ->label(__('console.grace_days'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                TextInput::make('price')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.price'))
                    ->label(__('console.price'))
                    ->helperText(__('console.price_hint'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->live()
                    ->required(),

                TextInput::make('currency_code')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.currency_code'))
                    ->label(__('console.currency'))
                    ->length(3)
                    ->alpha()
                    ->live(onBlur: true)
                    ->required(function (Get $get): bool {
                        $price = $get('price');

                        if (is_int($price) || is_float($price)) {
                            return $price > 0;
                        }

                        return is_string($price) && (int) $price > 0;
                    })
                    ->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? Str::upper($state) : null)
                    ->placeholder('USD'),

                TextInput::make('trial_days')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.trial_days'))
                    ->label(__('console.trial_days'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                Textarea::make('description')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.description'))
                    ->label(__('console.description'))
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->maxLength(1000),

                Toggle::make('active')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.active'))
                    ->label(__('console.active'))
                    ->columnSpanFull()
                    ->default(true)
                    ->live()
                    ->onIcon(Heroicon::Bolt)
                    ->required(),

                Toggle::make('is_default')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.is_default'))
                    ->label(__('console.is_default'))
                    ->helperText(__('console.is_default_hint'))
                    ->columnSpanFull()
                    ->default(false)
                    ->onIcon(Heroicon::Bolt)
                    ->visible(fn(Get $get): bool => (bool) $get('active'))
                    ->live()
                    ->required(),
            ])
            ->columns(2);
    }

    /**
     * @return array<string, string>
     */
    private static function periodUnitOptions(): array
    {
        return collect(PeriodUnit::cases())
            ->mapWithKeys(fn(PeriodUnit $unit): array => [$unit->value => ucfirst($unit->value)])
            ->all();
    }
}
