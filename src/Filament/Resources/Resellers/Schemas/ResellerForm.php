<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component as Livewire;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\ValidatesResellerUser;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Actions\GeneratePasswordAction;
use Misaf\VendraSupport\Filament\Forms\Components\IsActiveToggle;

final class ResellerForm
{
    use ValidatesResellerUser;

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
                    ->rules(self::resellerUsernameRules())
                    ->required()
                    ->visibleOn('create'),

                TextInput::make('email')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.email'))
                    ->label(__('vendra-console::attributes.email'))
                    ->email()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rules(fn (string $operation): array => $operation === 'create' ? self::resellerEmailRules() : [])
                    ->visibleOn('create'),

                NewPasswordInput::make()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->hintAction(
                        GeneratePasswordAction::make()->confirmationField('password_confirmation'),
                    )
                    ->visibleOn('create'),

                PasswordConfirmationInput::make()
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

                IsActiveToggle::make()
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
                            ->badge()
                            ->state(fn (?Reseller $record): ?string => $record?->activeSubscription()?->status->value)
                            ->formatStateUsing(fn (string $state): string => __("vendra-console::attributes.status_{$state}"))
                            ->placeholder('—'),

                        TextEntry::make('current_ends_at')
                            ->label(__('vendra-console::attributes.ends_at'))
                            ->state(fn (?Reseller $record): string => $record?->activeSubscription()?->ends_at?->toDayDateTimeString() ?? '—'),
                    ]),
            ])
            ->columns(2);
    }
}
