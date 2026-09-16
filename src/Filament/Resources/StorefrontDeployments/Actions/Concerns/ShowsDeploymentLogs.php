<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\StorefrontDeployment;

trait ShowsDeploymentLogs
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
            ->label(__('vendra-console::actions.view_logs'))
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
                    ->placeholder(__('vendra-console::messages.no_recent_logs'))
                    ->columnSpanFull(),
            ])
            ->modalHeading(__('vendra-console::attributes.recent_storefront_logs'))
            ->action(static fn (): null => null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('vendra-console::actions.close'));
    }
}
