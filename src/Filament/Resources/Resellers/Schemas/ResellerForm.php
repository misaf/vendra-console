<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as Livewire;
use Misaf\LaravelEmailValidation\Rules\EmailValidation;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraReseller\Models\ResellerUser;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Actions\GeneratePasswordAction;

final class ResellerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.username'))
                    ->label(__('console.username'))
                    ->live(onBlur: true)
                    ->minLength(3)
                    ->maxLength(12)
                    ->rules(['alpha_dash'])
                    ->required()
                    ->unique(
                        table: ResellerUser::class,
                        column: 'username',
                        modifyRuleUsing: fn(Unique $rule): Unique => $rule
                            ->withoutTrashed(),
                    )
                    ->visibleOn('create'),

                TextInput::make('email')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.email'))
                    ->label(__('console.email'))
                    ->email()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required(fn(string $operation): bool => 'create' === $operation)
                    ->rules(fn(string $operation): array => 'create' === $operation
                        ? [
                            'bail',
                            'email:rfc,strict,spoof,filter,filter_unicode',
                            new EmailValidation(),
                            Rule::unique(ResellerUser::class, 'email')->withoutTrashed(),
                        ]
                        : []),

                TextInput::make('password')
                    ->label(__('console.new_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->extraAttributes(['dir' => 'ltr'])
                    ->required()
                    ->confirmed()
                    ->rule(Password::default())
                    ->hintAction(
                        GeneratePasswordAction::make()->confirmationField('password_confirmation'),
                    )
                    ->visibleOn('create'),

                TextInput::make('password_confirmation')
                    ->label(__('console.confirm_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),

                Select::make('plan_id')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.plan_id'))
                    ->label(__('console.subscription_plan'))
                    ->live()
                    ->options(fn(): array => Plan::query()
                        ->active()
                        ->get()
                        ->mapWithKeys(fn(Plan $plan): array => [
                            $plan->id => "{$plan->name} — " . ($plan->isFree()
                                ? __('console.free')
                                : $plan->formattedPrice()),
                        ])
                        ->all())
                    ->required()
                    ->native(false)
                    ->visibleOn('create'),

                Toggle::make('active')
                    ->afterStateUpdated(fn(Livewire $livewire) => $livewire->validateOnly('data.active'))
                    ->label(__('console.active'))
                    ->columnSpanFull()
                    ->default(true)
                    ->live()
                    ->onIcon(Heroicon::Bolt)
                    ->required(),

                Section::make(__('console.current_subscription'))
                    ->visibleOn('edit')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('current_plan')
                            ->label(__('console.plan'))
                            ->state(fn(?Reseller $record): string => $record?->activeSubscription()?->plan->name ?? '—'),

                        TextEntry::make('current_status')
                            ->label(__('console.status'))
                            ->state(fn(?Reseller $record): string => $record?->activeSubscription()?->status->value ?? '—'),

                        TextEntry::make('current_ends_at')
                            ->label(__('console.ends_at'))
                            ->state(fn(?Reseller $record): string => $record?->activeSubscription()?->ends_at?->toDayDateTimeString() ?? '—'),
                    ]),
            ])
            ->columns(2);
    }
}
