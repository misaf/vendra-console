<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithStoreRecord;
use Misaf\VendraStore\Actions\OffboardStoreAction;
use Misaf\VendraStore\Models\Store;

final class OffboardStoreTableAction extends Action
{
    use InteractsWithStoreRecord;

    public static function getDefaultName(): string
    {
        return 'offboardStore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.offboard_store'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('danger')
            ->visible(fn (Store $record): bool => ! $record->trashed())
            ->schema([
                Textarea::make('reason')
                    ->label(__('console.offboarding_reason'))
                    ->required()
                    ->maxLength(OffboardStoreAction::MAX_REASON_LENGTH),
            ])
            ->action(function (Store $record, array $data, OffboardStoreAction $offboardStore): void {
                $offboardStore->execute($record, (string) Arr::get($data, 'reason'));
                self::notify(__('console.store_offboarded'));
            });
    }
}
