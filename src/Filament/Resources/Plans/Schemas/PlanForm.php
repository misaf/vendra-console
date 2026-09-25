<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Livewire\Component as Livewire;
use Misaf\VendraConsole\Support\PlatformCurrencies;
use Misaf\VendraSubscription\Enums\PeriodUnit;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Enums\PlanFeature;
use Misaf\VendraSupport\Enums\PlanLimit;
use Misaf\VendraSupport\Filament\Forms\Components\DescriptionTextarea;
use Misaf\VendraSupport\Filament\Forms\Components\IsActiveToggle;
use Misaf\VendraSupport\Filament\Forms\Components\IsDefaultToggle;

final class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.name'))
                    ->label(__('vendra-console::attributes.name'))
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required(),

                TextInput::make('max_units')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.max_units'))
                    ->label(__('vendra-console::attributes.max_units'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(1)
                    ->required(),

                Select::make('period_unit')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.period_unit'))
                    ->label(__('vendra-console::attributes.period_unit'))
                    ->live()
                    ->native(false)
                    ->options(PeriodUnit::class)
                    ->required(),

                TextInput::make('period_count')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.period_count'))
                    ->label(__('vendra-console::attributes.period_count'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(1)
                    ->required(),

                TextInput::make('grace_days')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.grace_days'))
                    ->label(__('vendra-console::attributes.grace_days'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                TextInput::make('price')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.price'))
                    ->label(__('vendra-console::attributes.price'))
                    ->helperText(__('vendra-console::attributes.price_hint'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->live()
                    ->required(),

                Select::make('currency_code')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.currency_code'))
                    ->label(__('vendra-console::attributes.currency'))
                    ->helperText(__('vendra-console::attributes.plan_currency_hint'))
                    ->options(fn (?Plan $record): array => PlatformCurrencies::options([$record?->currency_code]))
                    ->default(fn (): ?string => PlatformCurrencies::defaultCode())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->required(function (Get $get): bool {
                        $price = $get('price');

                        if (is_int($price) || is_float($price)) {
                            return $price > 0;
                        }

                        return is_string($price) && (int) $price > 0;
                    }),

                TextInput::make('trial_days')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.trial_days'))
                    ->label(__('vendra-console::attributes.trial_days'))
                    ->integer()
                    ->live(onBlur: true)
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                DescriptionTextarea::make()
                    ->maxLength(1000),

                IsActiveToggle::make()
                    ->default(true),

                IsDefaultToggle::make()
                    ->helperText(__('vendra-console::attributes.is_default_hint'))
                    ->visible(fn (Get $get): bool => (bool) $get('active')),

                Section::make(__('vendra-console::attributes.entitlements'))
                    ->schema([
                        CheckboxList::make('features')
                            ->label(__('vendra-console::attributes.features'))
                            ->options(PlanFeature::class)
                            ->columnSpanFull(),

                        Group::make(array_map(
                            fn (PlanLimit $limit): TextInput => TextInput::make($limit->value)
                                ->label($limit->getLabel())
                                ->placeholder(__('vendra-console::attributes.unlimited'))
                                ->integer()
                                ->minValue(0),
                            PlanLimit::cases(),
                        ))
                            ->statePath('limits')
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->description(__('vendra-console::attributes.limits_hint'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Drop empty limits, which mean unlimited, and store no map when none is set.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeLimits(array $data): array
    {
        $limits = [];

        foreach (Arr::array($data, 'limits', []) as $key => $limit) {
            if (is_numeric($limit)) {
                $limits[$key] = (int) $limit;
            }
        }

        $data['limits'] = $limits === [] ? null : $limits;

        return $data;
    }
}
