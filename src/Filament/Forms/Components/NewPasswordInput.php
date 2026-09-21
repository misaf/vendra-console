<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Misaf\VendraUser\Support\UserRules;

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
            ->rules(UserRules::password());
    }
}
