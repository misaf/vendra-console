<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns\InteractsWithDeploymentRecord;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\StorefrontDeployment;

final class ViewLogsTableAction extends Action
{
    use InteractsWithDeploymentRecord;

    public static function getDefaultName(): string
    {
        return 'viewLogs';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.view_logs'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->fillForm(fn (StorefrontDeployment $record, StorefrontProvisioner $provisioner): array => [
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
