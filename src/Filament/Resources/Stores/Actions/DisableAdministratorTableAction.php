<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\SetUserAccountEnabledAction;
use Misaf\VendraUser\Models\User;

final class DisableAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'disableAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.disable_account'))
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->trashed())
            ->action(fn (User $record, RelationManager $livewire, SetUserAccountEnabledAction $setAccountEnabled) => self::guardLastAdministrator(
                fn (): mixed => $setAccountEnabled->execute(self::administratorStore($livewire), $record, false),
                __('vendra-console::messages.account_disabled'),
            ));
    }
}
