<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('console.domain_history');
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
                    ->label(__('console.domain'))
                    ->icon(Heroicon::GlobeAlt)
                    ->searchable(),

                ToggleColumn::make('active')
                    ->label(__('console.active'))
                    ->onIcon(Heroicon::Bolt),

                TextColumn::make('created_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('console.created_at'))
                    ->sinceTooltip()
                    ->sortable()
                    ->when(
                        app()->isLocale('fa'),
                        fn(TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn(TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),

                TextColumn::make('deleted_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('console.replaced_at'))
                    ->sinceTooltip()
                    ->placeholder('—')
                    ->sortable()
                    ->when(
                        app()->isLocale('fa'),
                        fn(TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn(TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
