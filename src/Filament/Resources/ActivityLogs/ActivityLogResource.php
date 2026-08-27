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

/**
 * The platform's audit trail, across every tenant.
 *
 * Deliberately its own resource rather than the one `misaf/vendra-activity-log`
 * ships to the admin panel. That one is clustered and gated by the tenant
 * permission tables; a console operator is a `ConsoleUser` on the `console`
 * guard, holds no roles at all, and is trusted by panel access alone.
 *
 * The rows arrive unscoped for the same structural reason the console can list
 * every store: `TenantScope` applies only while a tenant is current, and the
 * console panel runs outside the tenant middleware. That is the point of the
 * view — one operator reading what happened across the fleet.
 */
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

    public static function table(Table $table): Table
    {
        return ActivityLogTable::configure($table);
    }

    /**
     * Console operators are trusted by panel access alone.
     *
     * `ActivityLog` carries a policy that resolves the *tenant* permission
     * tables. A `ConsoleUser` holds no roles there and would be denied by
     * default, so this panel states its own rule rather than borrowing one
     * written for a different guard. Everything else stays denied: the audit
     * trail is a record of what happened, and nothing here writes to it.
     */
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
