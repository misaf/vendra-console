<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
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
            ->label(__('console.view_logs'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (Store $record): bool => self::deployment($record) instanceof StorefrontDeployment)
            ->fillForm(fn (Store $record, StorefrontProvisioner $provisioner): array => [
                'logs' => self::logsFor($record, $provisioner),
            ])
            ->schema([
                Textarea::make('logs')
                    ->hiddenLabel()
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(20)
                    ->placeholder(__('console.no_recent_logs'))
                    ->columnSpanFull(),
            ])
            ->modalHeading(__('console.recent_storefront_logs'))
            ->action(static fn (): null => null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('console.close'));
    }
}
