<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\StopStoreStorefrontAction;
use Misaf\VendraStore\Enums\StorefrontDesiredState;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class StopStorefrontTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'stopStorefront';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.stop_storefront'))
            ->icon(Heroicon::OutlinedStop)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Store $record): bool => self::deployment($record)?->desired_state === StorefrontDesiredState::Running)
            ->action(function (Store $record, StopStoreStorefrontAction $stopStorefront): void {
                $deployment = self::deployment($record);

                if ($deployment instanceof StorefrontDeployment) {
                    self::run(
                        fn (): mixed => $stopStorefront->execute($deployment),
                        __('console.storefront_stopped'),
                    );
                }
            });
    }
}
