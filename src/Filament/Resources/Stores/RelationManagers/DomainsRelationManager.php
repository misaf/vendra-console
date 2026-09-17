<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\DeletedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;

final class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('vendra-console::attributes.domain_history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('vendra-console::attributes.domain'))
                    ->icon(Heroicon::GlobeAlt)
                    ->searchable(),

                IsActiveIconColumn::make(),

                CreatedAtColumn::make()
                    ->sortable(),

                DeletedAtColumn::make()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
