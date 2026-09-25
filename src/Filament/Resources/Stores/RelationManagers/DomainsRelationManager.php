<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AddDomainAliasTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RemoveDomainAliasTableAction;
use Misaf\VendraStore\Filament\RelationManagers\DomainsRelationManager as BaseDomainsRelationManager;

final class DomainsRelationManager extends BaseDomainsRelationManager
{
    protected function addDomainAliasAction(): AddDomainAliasTableAction
    {
        return AddDomainAliasTableAction::make();
    }

    protected function removeDomainAliasAction(): RemoveDomainAliasTableAction
    {
        return RemoveDomainAliasTableAction::make();
    }
}
