<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\RemoveTenantAdministratorAction;
use Misaf\VendraUser\Models\User;

final class RemoveAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'removeAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.remove_administrator'))
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->trashed())
            ->action(fn (User $record, RelationManager $livewire, RemoveTenantAdministratorAction $removeAdministrator) => self::guardLastAdministrator(
                function () use ($removeAdministrator, $livewire, $record): void {
                    $removeAdministrator->execute(self::administratorStore($livewire), $record);
                },
                __('vendra-console::messages.administrator_removed'),
            ));
    }
}
