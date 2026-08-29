<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;

final class ResellerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('console.reseller_overview'))
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('name')
                            ->label(__('console.name')),
                        TextEntry::make('slug')
                            ->label(__('console.reseller_identifier'))
                            ->copyable(),
                        TextEntry::make('email')
                            ->label(__('console.email'))
                            ->placeholder('—')
                            ->copyable(),
                        IconEntry::make('active')
                            ->label(__('console.active'))
                            ->boolean(),
                        TextEntry::make('stores_count')
                            ->label(__('console.stores_count')),
                    ]),
                    TextEntry::make('description')
                        ->label(__('console.description'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make(__('console.owner_account'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('owner_username')
                            ->label(__('console.username'))
                            ->state(fn(Reseller $record): ?string => $record->ownerUser?->username)
                            ->placeholder('—'),
                        TextEntry::make('owner_email')
                            ->label(__('console.email'))
                            ->state(fn(Reseller $record): ?string => $record->ownerUser?->email)
                            ->placeholder('—')
                            ->copyable(),
                    ]),
                ])
                ->columnSpanFull(),
            Section::make(__('console.current_subscription'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('current_plan')
                            ->label(__('console.plan'))
                            ->state(fn(Reseller $record): ?string => self::subscription($record)?->plan?->name)
                            ->placeholder('—'),
                        TextEntry::make('current_status')
                            ->label(__('console.subscription_status'))
                            ->badge()
                            ->state(fn(Reseller $record): ?string => self::subscription($record)?->status->value)
                            ->formatStateUsing(fn(string $state): string => __("console.status_{$state}"))
                            ->placeholder('—'),
                        TextEntry::make('current_ends_at')
                            ->label(__('console.ends_at'))
                            ->state(fn(Reseller $record): ?string => self::subscription($record)?->ends_at?->toDayDateTimeString())
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
