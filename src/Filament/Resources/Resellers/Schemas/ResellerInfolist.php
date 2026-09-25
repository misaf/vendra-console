<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Misaf\VendraConsole\Filament\Resources\Invoices\InvoiceResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Enums\SubscriptionStatus;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Enums\PlanFeature;
use Misaf\VendraSupport\Filament\Infolists\Components\IsActiveEntry;

final class ResellerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('vendra-console::attributes.reseller_overview'))
                ->schema([
                    Grid::make(2)->schema([
                        IsActiveEntry::make(),
                        TextEntry::make('stores_count')
                            ->label(__('vendra-console::attributes.stores_count')),
                    ]),
                ])
                ->columnSpanFull(),
            Section::make(__('vendra-console::attributes.user_account'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('user.username')
                            ->label(__('vendra-console::attributes.username')),
                        TextEntry::make('user.email')
                            ->label(__('vendra-console::attributes.email'))
                            ->copyable(),
                    ]),
                ])
                ->columnSpanFull(),
            Section::make(__('vendra-console::attributes.current_subscription'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('current_plan')
                            ->label(__('vendra-console::navigation.plan'))
                            ->state(fn (Reseller $record): ?string => self::subscription($record)?->plan?->name)
                            ->placeholder('—'),
                        TextEntry::make('current_status')
                            ->label(__('vendra-console::attributes.subscription_status'))
                            ->badge()
                            ->state(fn (Reseller $record): ?SubscriptionStatus => self::subscription($record)?->status)
                            ->placeholder('—'),
                        TextEntry::make('current_ends_at')
                            ->label(__('vendra-console::attributes.ends_at'))
                            ->state(fn (Reseller $record): ?CarbonInterface => self::subscription($record)?->ends_at)
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),
                        IconEntry::make('has_priority_support')
                            ->label(PlanFeature::PrioritySupport->getLabel())
                            ->boolean(),
                    ]),
                ])
                ->columnSpanFull(),
            Section::make(__('vendra-console::attributes.wallet'))
                ->schema([
                    TextEntry::make('wallet_balances')
                        ->label(__('vendra-console::attributes.wallet_balance'))
                        ->state(fn (Reseller $record): array => $record->formattedWalletBalances())
                        ->listWithLineBreaks()
                        ->placeholder('—'),
                    TextEntry::make('invoices')
                        ->label(__('vendra-console::attributes.invoices'))
                        ->state(fn (Reseller $record): int => $record->invoices()->count())
                        ->suffixAction(
                            Action::make('viewInvoices')
                                ->label(__('vendra-console::attributes.view_invoices'))
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->url(fn (Reseller $record): string => InvoiceResource::getUrl('index', ['filters' => ['reseller' => ['value' => $record->id]]])),
                        ),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    private static function subscription(Reseller $reseller): ?Subscription
    {
        $subscription = $reseller->subscriptions->first();

        return $subscription instanceof Subscription ? $subscription : null;
    }
}
