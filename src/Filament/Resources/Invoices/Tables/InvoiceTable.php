<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Invoices\Tables;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\SubscriptionInvoice;
use Misaf\VendraSubscription\Support\SubscriptionInvoicePdf;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('number')
                    ->label(__('vendra-console::attributes.invoice_number'))
                    ->searchable(),

                TextColumn::make('buyer_name')
                    ->label(__('vendra-console::navigation.reseller'))
                    ->icon(Heroicon::OutlinedUser)
                    ->state(fn (SubscriptionInvoice $record): string => Arr::string($record->buyer, 'name')),

                TextColumn::make('issued_at')
                    ->label(__('vendra-console::attributes.issued_at'))
                    ->date()
                    ->sortable(),

                TextColumn::make('tax_amount')
                    ->label(__('vendra-console::attributes.invoice_tax'))
                    ->state(fn (SubscriptionInvoice $record): string => $record->formattedAmount($record->tax_amount)),

                TextColumn::make('total_amount')
                    ->label(__('vendra-console::attributes.invoice_total'))
                    ->state(fn (SubscriptionInvoice $record): string => $record->formattedTotal()),
            ])
            ->description(__('vendra-console::tables.description.invoices'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.invoices'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.invoices'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->filters(
                [
                    SelectFilter::make('reseller')
                        ->label(__('vendra-console::navigation.reseller'))
                        ->searchable()
                        ->options(fn (): array => Reseller::displayNames(Reseller::query()->withTrashed()))
                        ->query(fn (Builder $query, array $data): Builder => $query->when(
                            Arr::get($data, 'value', null),
                            fn (Builder $query, mixed $resellerId): Builder => $query
                                ->where('subscriber_type', (new Reseller)->getMorphClass())
                                ->where('subscriber_id', $resellerId),
                        )),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->recordActions([
                Action::make('download')
                    ->label(__('vendra-console::actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (SubscriptionInvoice $record): StreamedResponse => response()->streamDownload(
                        function () use ($record): void {
                            echo SubscriptionInvoicePdf::render($record);
                        },
                        $record->downloadName(),
                        ['Content-Type' => 'application/pdf'],
                    )),
            ])
            ->defaultSort(column: 'issued_at', direction: 'desc');
    }
}
