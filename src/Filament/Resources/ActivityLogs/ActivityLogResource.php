<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\ActivityLogs;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraActivityLog\Models\ActivityLog;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use Misaf\VendraConsole\Filament\Resources\ActivityLogs\Tables\ActivityLogTable;

final class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $slug = 'activity';

    public static function getModelLabel(): string
    {
        return __('console.activity_log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.activity_logs');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.activity_logs');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function getNavigationSort(): int
    {
        return 6;
    }

    public static function table(Table $table): Table
    {
        return ActivityLogTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
