<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\ActivateStorefrontImageTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\DeactivateStorefrontImageTableAction;
use Misaf\VendraConsole\Filament\Resources\StorefrontImages\Actions\DeleteStorefrontImageTableAction;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Filters\IsActiveFilter;

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
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                IsActiveIconColumn::make(),

                CreatedAtColumn::make()
                    ->sortable(),

                UpdatedAtColumn::make(),
            ])
            ->description(__('vendra-console::tables.description.storefront_images'))
            ->emptyStateHeading(__('vendra-console::tables.empty_state.heading.storefront_images'))
            ->emptyStateDescription(__('vendra-console::tables.empty_state.description.storefront_images'))
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->filters([
                IsActiveFilter::make(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeactivateStorefrontImageTableAction::make(),
                    ActivateStorefrontImageTableAction::make(),
                    DeleteStorefrontImageTableAction::make(),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
