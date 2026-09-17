<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class ViewStorefrontLogsTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'viewStorefrontLogs';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->showsStorefrontLogs()
            ->visible(fn (Store $record): bool => $record->storefrontDeployment instanceof StorefrontDeployment);
    }
}
