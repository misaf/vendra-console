<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraConsole\Support\PlatformCurrencies;
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
                Select::make('currency_code')
                    ->label(__('vendra-console::attributes.currency'))
                    ->options(fn (Reseller $record): array => self::currencyOptions($record))
                    ->default(fn (Reseller $record): ?string => self::displayedLatestSubscription($record)->currency_code ?? PlatformCurrencies::defaultCode())
                    ->native(false)
                    ->searchable()
                    ->required(),
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

    /**
     * Offer the platform's currencies and any the reseller is already billed or holds a balance in.
     *
     * @return array<string, string>
     */
    private static function currencyOptions(Reseller $reseller): array
    {
        return PlatformCurrencies::options([
            self::displayedLatestSubscription($reseller)?->currency_code,
            ...$reseller->walletCurrencyCodes(),
        ]);
    }
}
