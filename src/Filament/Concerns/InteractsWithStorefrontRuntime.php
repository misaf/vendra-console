<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Concerns;

use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontRuntimeSnapshots;
use PDOException;
use Throwable;

trait InteractsWithStorefrontRuntime
{
    /**
     * @param  callable(): mixed  $operation
     */
    protected static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            report($exception);

            // A database error message carries the SQL and its bindings, so only the title is shown.
            Notification::make()
                ->danger()
                ->title(__('vendra-console::messages.operational_action_failed'))
                ->body($exception instanceof PDOException ? null : $exception->getMessage())
                ->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    protected static function logsFor(?StorefrontDeployment $deployment, StorefrontRuntimeSnapshots $snapshots): string
    {
        if (! $deployment instanceof StorefrontDeployment) {
            return __('vendra-console::messages.no_recent_logs');
        }

        try {
            $snapshot = $snapshots->latest($deployment, logs: true);

            if ($snapshot === null) {
                return __('vendra-console::messages.runtime_read_pending');
            }

            if ($snapshot->error !== null) {
                return __('vendra-console::messages.runtime_unavailable_message', ['message' => $snapshot->error]);
            }

            $logs = $snapshot->logs ?? '';

            return mb_trim($logs) === '' ? __('vendra-console::messages.no_recent_logs') : $logs;
        } catch (Throwable $exception) {
            report($exception);

            return __('vendra-console::messages.runtime_unavailable_message', ['message' => $exception->getMessage()]);
        }
    }

    protected function showsStorefrontLogs(): static
    {
        return $this
            ->label(__('vendra-console::actions.view_logs'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                TextEntry::make('logs')
                    ->hiddenLabel()
                    ->state(fn (Model $record, StorefrontRuntimeSnapshots $snapshots): string => self::logsFor(self::storefrontDeploymentFor($record), $snapshots))
                    ->extraAttributes([
                        'wire:poll.5s' => '$refresh',
                        'style' => 'white-space: pre-wrap; overflow-wrap: anywhere; max-height: 32rem; overflow-y: auto;',
                    ])
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
            $record instanceof Store => $record->storefrontDeployment()->first(),
            default => null,
        };
    }
}
