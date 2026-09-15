<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\SetUserAccountEnabledAction;
use Misaf\VendraUser\Models\User;

final class EnableAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'enableAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.enable_account'))
            ->visible(fn (User $record): bool => $record->trashed())
            ->action(function (User $record, RelationManager $livewire, SetUserAccountEnabledAction $setAccountEnabled): void {
                $setAccountEnabled->execute(self::administratorStore($livewire), $record, true);
                self::notifySuccess(__('vendra-console::messages.account_enabled'));
            });
    }
}
