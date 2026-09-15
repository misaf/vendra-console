<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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

                IconColumn::make('active')
                    ->label(__('vendra-console::attributes.active'))
                    ->boolean()
                    ->trueIcon(Heroicon::Bolt),

                TextColumn::make('created_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('vendra-console::attributes.created_at'))
                    ->sinceTooltip()
                    ->sortable()
                    ->when(
                        app()->isLocale('fa'),
                        fn (TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn (TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),

                TextColumn::make('deleted_at')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->label(__('vendra-console::attributes.replaced_at'))
                    ->sinceTooltip()
                    ->placeholder('—')
                    ->sortable()
                    ->when(
                        app()->isLocale('fa'),
                        fn (TextColumn $column) => $column->jalaliDateTime('Y-m-d H:i', latinNumbers: true),
                        fn (TextColumn $column) => $column->dateTime('Y-m-d H:i')
                    ),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
