<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontDeployments\Actions\Concerns;

use Filament\Notifications\Notification;
use Misaf\VendraStore\Contracts\StorefrontProvisioner;
use Misaf\VendraStore\Models\StorefrontDeployment;
use Misaf\VendraStore\Support\StorefrontReference;
use Throwable;

trait InteractsWithDeploymentRecord
{
    protected static function logsFor(
        StorefrontDeployment $deployment,
        StorefrontProvisioner $provisioner,
    ): string {
        try {
            $logs = $provisioner->logs(StorefrontReference::for($deployment));

            return mb_trim($logs) === '' ? __('console.no_recent_logs') : $logs;
        } catch (Throwable $exception) {
            report($exception);

            return __('console.runtime_unavailable_message', ['message' => $exception->getMessage()]);
        }
    }

    /** @param callable(): mixed $operation */
    protected static function run(callable $operation, string $successTitle): mixed
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
