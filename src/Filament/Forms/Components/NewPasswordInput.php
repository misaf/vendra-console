<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rules\Password;

final class NewPasswordInput extends TextInput
{
    public static function getDefaultName(): string
    {
        return 'password';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::attributes.new_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->confirmed()
            ->rule(Password::default());
    }
}
