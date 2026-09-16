<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

final class PasswordConfirmationInput extends TextInput
{
    public static function getDefaultName(): string
    {
        return 'password_confirmation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::attributes.confirm_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->dehydrated(false);
    }
}
