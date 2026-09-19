<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\ActivityLogResource;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;

final class RecentActivity extends TableWidget
{
    private const int ROWS = 8;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('vendra-console::attributes.recent_activity'))
            ->query(fn (): Builder => ActivityLog::query()->latest('id')->limit(self::ROWS))
            ->columns([
                TextColumn::make('description')
                    ->label(__('vendra-console::attributes.description'))
                    ->wrap(),
                TextColumn::make('event')
                    ->label(__('vendra-console::attributes.event'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('subject_type')
                    ->label(__('vendra-console::attributes.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->description(fn (ActivityLog $record): ?string => $record->subject_id === null ? null : '#'.$record->subject_id)
                    ->placeholder('—'),
                CreatedAtColumn::make(),
            ])
            ->headerActions([
                Action::make('viewAllActivity')
                    ->label(__('vendra-console::actions.view_all'))
                    ->link()
                    ->url(ActivityLogResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
