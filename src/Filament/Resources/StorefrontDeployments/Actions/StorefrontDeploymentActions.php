<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraStore\Actions\ReconcileStoreStorefrontAction;
use Misaf\VendraStore\Actions\RestartStoreStorefrontAction;
use Misaf\VendraStore\Actions\RetryFailedStorefrontDeploymentAction;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Enums\StorefrontDeploymentStatus;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontReference;
use Throwable;

final class StorefrontDeploymentActions
{
    public static function logs(): Action
    {
        return Action::make('viewLogs')
            ->label(__('console.view_logs'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->fillForm(fn(StorefrontDeployment $record, StorefrontProvisioner $provisioner): array => [
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
            ->action(static fn(): null => null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('console.close'));
    }

    public static function retry(): Action
    {
        return Action::make('retryDeployment')
            ->label(__('console.retry_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn(StorefrontDeployment $record): bool => StorefrontDeploymentStatus::Failed === $record->status)
            ->action(fn(
                StorefrontDeployment $record,
                RetryFailedStorefrontDeploymentAction $retry,
            ): mixed => self::run(
                fn(): mixed => $retry->execute($record),
                __('console.storefront_retry_queued'),
            ));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcileDeployment')
            ->label(__('console.reconcile_storefront'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->requiresConfirmation()
            ->action(fn(
                StorefrontDeployment $record,
                ReconcileStoreStorefrontAction $reconcile,
            ): mixed => self::run(
                fn(): mixed => $reconcile->execute($record),
                __('console.storefront_reconciled'),
            ));
    }

    public static function restart(): Action
    {
        return Action::make('restartDeployment')
            ->label(__('console.restart_storefront'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn(
                StorefrontDeployment $record,
                RestartStoreStorefrontAction $restart,
            ): mixed => self::run(
                fn(): mixed => $restart->execute($record),
                __('console.storefront_restarted'),
            ));
    }

    private static function logsFor(
        StorefrontDeployment $deployment,
        StorefrontProvisioner $provisioner,
    ): string {
        try {
            $logs = $provisioner->logs(StorefrontReference::for($deployment));

            return '' === mb_trim($logs) ? __('console.no_recent_logs') : $logs;
        } catch (Throwable $exception) {
            report($exception);

            return __('console.runtime_unavailable_message', ['message' => $exception->getMessage()]);
        }
    }

    /** @param callable(): mixed $operation */
    private static function run(callable $operation, string $successTitle): mixed
    {
        try {
            $result = $operation();

            Notification::make()->success()->title($successTitle)->send();

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('console.operational_action_failed'))
                ->body($exception->getMessage())
                ->send();

            return null;
        }
    }
}
