<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\Store;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontReference;
use Throwable;

trait InteractsWithStoreRecord
{
    protected static function deployment(Store $store): ?StorefrontDeployment
    {
        $deployment = $store->storefrontDeployments->first();

        return $deployment instanceof StorefrontDeployment ? $deployment : null;
    }

    protected static function logsFor(Store $store, StorefrontProvisioner $provisioner): string
    {
        $deployment = self::deployment($store);

        if (! $deployment instanceof StorefrontDeployment) {
            return __('console.no_recent_logs');
        }

        try {
            $logs = $provisioner->logs(StorefrontReference::for($deployment));

            return mb_trim($logs) === '' ? __('console.no_recent_logs') : $logs;
        } catch (Throwable $exception) {
            report($exception);

            return __('console.runtime_unavailable_message', ['message' => $exception->getMessage()]);
        }
    }

    /** @param callable(): mixed $operation */
    protected static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
            self::notify($successTitle);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('console.operational_action_failed'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected static function notify(string $title): void
    {
        Notification::make()->success()->title($title)->send();
    }
}
