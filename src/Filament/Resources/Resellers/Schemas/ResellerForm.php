<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Component as Livewire;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\ValidatesResellerUser;
use Misaf\VendraSubscription\Models\Plan;
use Misaf\VendraSupport\Filament\Actions\GeneratePasswordAction;
use Misaf\VendraSupport\Filament\Forms\Components\IsActiveToggle;
use Misaf\VendraUser\Support\UserRules;

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
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->minLength(UserRules::USERNAME_MIN_LENGTH)
                    ->maxLength(UserRules::USERNAME_MAX_LENGTH)
                    ->rules(self::resellerUsernameRules())
                    ->required(),

                TextInput::make('email')
                    ->afterStateUpdated(fn (Livewire $livewire) => $livewire->validateOnly('data.email'))
                    ->label(__('vendra-console::attributes.email'))
                    ->email()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->required()
                    ->rules(self::resellerEmailRules()),

                NewPasswordInput::make()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->hintAction(
                        GeneratePasswordAction::make()->confirmationField('password_confirmation'),
                    ),

                PasswordConfirmationInput::make(),

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
                    ->native(false),

                IsActiveToggle::make()
                    ->default(true),
            ])
            ->columns(2);
    }
}
