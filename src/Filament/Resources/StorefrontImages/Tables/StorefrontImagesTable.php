<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraStore\Actions\DeleteStorefrontImageAction;
use Misaf\VendraStore\Actions\UpdateStorefrontImageAction;
use Misaf\VendraStore\Models\StorefrontImage;
use Misaf\VendraSupport\Filament\Tables\Columns\ActiveToggleColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;

final class StorefrontImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                RowIndexColumn::make(),

                TextColumn::make('image')
                    ->label(__('vendra-console::attributes.storefront_image_reference'))
                    ->icon(Heroicon::Cube)
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('notes')
                    ->label(__('vendra-console::attributes.storefront_image_notes'))
                    ->wrap()
                    ->placeholder('—')
                    ->limit(60),

                ActiveToggleColumn::make()
                    ->updateStateUsing(function (StorefrontImage $record, bool $state, UpdateStorefrontImageAction $updateImage): bool {
                        $updateImage->execute($record, ['active' => $state]);

                        return $state;
                    }),

                TextColumn::make('created_at')
                    ->label(__('vendra-console::attributes.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('vendra-console::attributes.updated_at'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->description(__('vendra-console::tables.description.storefront_images'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.storefront_images'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.storefront_images'))
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('vendra-console::attributes.active'))
                    ->trueLabel(__('vendra-console::attributes.active'))
                    ->falseLabel(__('vendra-console::attributes.inactive'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('active', true),
                        false: fn (Builder $query): Builder => $query->where('active', false),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->hidden(fn (StorefrontImage $record): bool => $record->isInUse())
                        ->using(function (StorefrontImage $record, DeleteStorefrontImageAction $deleteImage): bool {
                            $deleteImage->execute($record);

                            return true;
                        }),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
