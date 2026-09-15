<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\DemoteTenantAdministratorAction;
use Misaf\VendraUser\Models\User;

final class DemoteAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'demoteAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.demote_administrator'))
            ->visible(fn (User $record, RelationManager $livewire): bool => ! $record->trashed()
                && self::isAdministrator(self::administratorStore($livewire), $record))
            ->action(fn (User $record, RelationManager $livewire, DemoteTenantAdministratorAction $demoteAdministrator) => self::guardLastAdministrator(
                fn (): mixed => $demoteAdministrator->execute(self::administratorStore($livewire), $record),
                __('vendra-console::messages.administrator_demoted'),
            ));
    }
}
