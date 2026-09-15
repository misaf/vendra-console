<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraUser\Actions\PromoteTenantAdministratorAction;
use Misaf\VendraUser\Models\User;

final class PromoteAdministratorTableAction extends Action
{
    use InteractsWithAdministratorRecord;

    public static function getDefaultName(): string
    {
        return 'promoteAdministrator';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.promote_administrator'))
            ->visible(fn (User $record, RelationManager $livewire): bool => ! $record->trashed()
                && ! self::isAdministrator(self::administratorStore($livewire), $record))
            ->action(function (User $record, RelationManager $livewire, PromoteTenantAdministratorAction $promoteAdministrator): void {
                $promoteAdministrator->execute(self::administratorStore($livewire), $record);
                self::notifySuccess(__('vendra-console::messages.administrator_promoted'));
            });
    }
}
