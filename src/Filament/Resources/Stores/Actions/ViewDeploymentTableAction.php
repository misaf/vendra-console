<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\StorefrontDeploymentResource;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class ViewDeploymentTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'viewDeployment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.view_deployment'))
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (Store $record): bool => self::deployment($record) instanceof StorefrontDeployment)
            ->url(function (Store $record): ?string {
                $deployment = self::deployment($record);

                return $deployment instanceof StorefrontDeployment
                    ? StorefrontDeploymentResource::getUrl('view', ['record' => $deployment])
                    : null;
            });
    }
}
