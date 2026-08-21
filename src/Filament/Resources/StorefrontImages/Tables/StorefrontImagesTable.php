<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\StorefrontImages\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraStore\Models\StorefrontImage;

final class StorefrontImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(['id']),

                TextColumn::make('name')
                    ->label(__('console.name'))
                    ->icon(Heroicon::Cube)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('image')
                    ->label(__('console.storefront_image_reference'))
                    ->copyable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('themes')
                    ->label(__('console.storefront_themes'))
                    ->badge(),

                ToggleColumn::make('active')
                    ->label(__('console.active'))
                    ->onIcon(Heroicon::Bolt),

                TextColumn::make('created_at')
                    ->label(__('console.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('console.updated_at'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->description(__('console.tables.description.storefront_images'))
            ->emptyStateHeading(__('console.tables.empty_state.heading.storefront_images'))
            ->emptyStateDescription(__('console.tables.empty_state.description.storefront_images'))
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('console.active'))
                    ->trueLabel(__('console.active'))
                    ->falseLabel(__('console.inactive'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->where('active', true),
                        false: fn(Builder $query): Builder => $query->where('active', false),
                        blank: fn(Builder $query): Builder => $query,
                    ),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->hidden(fn(StorefrontImage $record): bool => $record->isInUse()),
                ]),
            ])
            ->defaultSort(column: 'id', direction: 'desc');
    }
}
