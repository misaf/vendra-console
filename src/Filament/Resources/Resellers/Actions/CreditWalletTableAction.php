<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\CreditResellerWalletAction;
use Misaf\VendraReseller\Models\Reseller;

final class CreditWalletTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'creditWallet';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.credit_wallet'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->slideOver()
            ->schema([
                TextInput::make('amount')
                    ->label(__('vendra-console::attributes.amount'))
                    ->helperText(__('vendra-console::attributes.amount_hint'))
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->dehydrateStateUsing(fn (int|string $state): int => (int) $state),
                TextInput::make('currency_code')
                    ->label(__('vendra-console::attributes.currency'))
                    ->default(fn (Reseller $record): ?string => self::displayedLatestSubscription($record)?->currency_code)
                    ->length(3)
                    ->alpha()
                    ->required()
                    ->dehydrateStateUsing(fn (string $state): string => Str::upper($state))
                    ->placeholder('USD'),
                Textarea::make('note')
                    ->label(__('vendra-console::attributes.credit_note'))
                    ->helperText(__('vendra-console::attributes.credit_note_hint'))
                    ->maxLength(255)
                    ->required(),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(CreditResellerWalletAction::class)->execute(
                    $record,
                    Arr::integer($data, 'amount'),
                    Arr::string($data, 'currency_code'),
                    Arr::string($data, 'note'),
                );

                self::notifySuccess(__('vendra-console::messages.wallet_credited'));
            });
    }
}
