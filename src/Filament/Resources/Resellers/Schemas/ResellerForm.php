<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as Livewire;
use Misaf\LaravelEmailVerification\Rules\EmailValidation;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Actions\GeneratePasswordAction;
use Misaf\VendraSupport\Filament\Forms\Components\ActiveToggle;
use Misaf\VendraUser\Models\User;

final class ResellerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.username'))
                    ->label(__('vendra-console::attributes.username'))
                    ->live(onBlur: true)
                    ->minLength(3)
                    ->maxLength(12)
                    ->rules(['alpha_dash'])
                    ->required()
                    ->unique(
                        table: User::class,
                        column: 'username',
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                            ->withoutTrashed(),
                    )
                    ->visibleOn('create'),

                TextInput::make('email')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.email'))
                    ->label(__('vendra-console::attributes.email'))
                    ->email()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rules(fn (string $operation): array => $operation === 'create'
                        ? [
                            'bail',
                            'email:rfc,strict,spoof,filter,filter_unicode',
                            new EmailValidation,
                            Rule::unique(User::class, 'email')->withoutTrashed(),
                        ]
                        : [])
                    ->visibleOn('create'),

                TextInput::make('password')
                    ->label(__('vendra-console::attributes.new_password'))
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
                    ->label(__('vendra-console::attributes.confirm_password'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),

                Select::make('plan_id')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.plan_id'))
                    ->label(__('vendra-console::attributes.subscription_plan'))
                    ->live()
                    ->options(fn (): array => Plan::query()
                        ->active()
                        ->get()
                        ->mapWithKeys(fn (Plan $plan): array => [
                            $plan->id => "{$plan->name} — ".($plan->isFree()
                                ? __('vendra-console::attributes.free')
                                : $plan->formattedPrice()),
                        ])
                        ->all())
                    ->required()
                    ->native(false)
                    ->visibleOn('create'),

                ActiveToggle::make()
                    ->default(true)
                    ->visibleOn('create'),

                Section::make(__('vendra-console::attributes.current_subscription'))
                    ->visibleOn('edit')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('current_plan')
                            ->label(__('vendra-console::navigation.plan'))
                            ->state(fn (?Reseller $record): string => $record?->activeSubscription()?->plan->name ?? '—'),

                        TextEntry::make('current_status')
                            ->label(__('vendra-console::attributes.status'))
                            ->state(fn (?Reseller $record): string => $record?->activeSubscription()?->status->value ?? '—'),

                        TextEntry::make('current_ends_at')
                            ->label(__('vendra-console::attributes.ends_at'))
                            ->state(fn (?Reseller $record): string => $record?->activeSubscription()?->ends_at?->toDayDateTimeString() ?? '—'),
                    ]),
            ])
            ->columns(2);
    }
}
