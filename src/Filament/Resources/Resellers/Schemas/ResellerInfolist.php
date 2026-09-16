<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Filament\Infolists\Components\DescriptionEntry;
use Misaf\VendraSupport\Filament\Infolists\Components\IsActiveEntry;
use Misaf\VendraSupport\Filament\Infolists\Components\NameEntry;
use Misaf\VendraSupport\Filament\Infolists\Components\SlugEntry;

final class ResellerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('vendra-console::attributes.reseller_overview'))
                ->schema([
                    Grid::make(4)->schema([
                        NameEntry::make(),
                        SlugEntry::make()
                            ->label(__('vendra-console::attributes.reseller_identifier'))
                            ->copyable(),
                        TextEntry::make('email')
                            ->label(__('vendra-console::attributes.email'))
                            ->placeholder('—')
                            ->copyable(),
                        IsActiveEntry::make(),
                        TextEntry::make('stores_count')
                            ->label(__('vendra-console::attributes.stores_count')),
                    ]),
                    DescriptionEntry::make()
                        ->placeholder('—'),
                ])
                ->columnSpanFull(),
            Section::make(__('vendra-console::attributes.user_account'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('user_username')
                            ->label(__('vendra-console::attributes.username'))
                            ->state(fn (Reseller $record): ?string => $record->user()?->username)
                            ->placeholder('—'),
                        TextEntry::make('user_email')
                            ->label(__('vendra-console::attributes.email'))
                            ->state(fn (Reseller $record): ?string => $record->user()?->email)
                            ->placeholder('—')
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
                            ->state(fn (Reseller $record): ?string => self::subscription($record)?->status->value)
                            ->formatStateUsing(fn (string $state): string => __("vendra-console::attributes.status_{$state}"))
                            ->placeholder('—'),
                        TextEntry::make('current_ends_at')
                            ->label(__('vendra-console::attributes.ends_at'))
                            ->state(fn (Reseller $record): ?string => self::subscription($record)?->ends_at?->toDayDateTimeString())
                            ->placeholder('—'),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function subscription(Reseller $reseller): ?Subscription
    {
        $subscription = $reseller->subscriptions->first();

        return $subscription instanceof Subscription ? $subscription : null;
    }
}
