<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Concerns;

use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontReference;
use Throwable;

/**
 * Storefront runtime plumbing shared by the store and storefront deployment
 * actions: running an operation behind a notification and showing recent logs.
 */
trait InteractsWithStorefrontRuntime
{
    /**
     * Runs a storefront operation, reporting a failure as a notification
     * instead of an error page.
     *
     * @param  callable(): mixed  $operation
     */
    protected static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('vendra-console::messages.operational_action_failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    protected static function logsFor(?StorefrontDeployment $deployment, StorefrontProvisioner $provisioner): string
    {
        if (! $deployment instanceof StorefrontDeployment) {
            return __('vendra-console::messages.no_recent_logs');
        }

        try {
            $logs = $provisioner->logs(StorefrontReference::for($deployment));

            return mb_trim($logs) === '' ? __('vendra-console::messages.no_recent_logs') : $logs;
        } catch (Throwable $exception) {
            report($exception);

            return __('vendra-console::messages.runtime_unavailable_message', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Turns the action into a read-only modal of the record's recent storefront
     * logs, for a store or a storefront deployment record.
     */
    protected function showsStorefrontLogs(): static
    {
        return $this
            ->label(__('vendra-console::actions.view_logs'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->fillForm(fn (Model $record, StorefrontProvisioner $provisioner): array => [
                'logs' => self::logsFor(self::storefrontDeploymentFor($record), $provisioner),
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

    private static function storefrontDeploymentFor(Model $record): ?StorefrontDeployment
    {
        return match (true) {
            $record instanceof StorefrontDeployment => $record,
            $record instanceof Store => $record->storefrontDeployment,
            default => null,
        };
    }
}
