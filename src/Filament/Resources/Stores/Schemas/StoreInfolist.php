<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Resources\Stores\Schemas\StoreInfolist as BaseStoreInfolist;

final class StoreInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return BaseStoreInfolist::configure($schema, identityEntries: [
            TextEntry::make('reseller_id')
                ->label(__('vendra-console::navigation.reseller'))
                ->formatStateUsing(fn (?int $state): string => $state === null
                    ? __('vendra-console::attributes.platform_owned_store')
                    : Reseller::query()->withTrashed()->find($state)?->name ?? '—'),
        ]);
    }
}
