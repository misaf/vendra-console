<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\StartStoreStorefrontAction;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class StartStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'startStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.start_storefront'))
            ->icon(Heroicon::OutlinedPlay)
            ->visible(fn (Store $record): bool => self::deployment($record)?->desired_state === StorefrontDesiredState::Stopped)
            ->action(function (Store $record, StartStoreStorefrontAction $startStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    self::run(
                        fn (): mixed => $startStorefront->execute($deployment),
                        __('console.storefront_started'),
                    );
                }
            });
    }
}
