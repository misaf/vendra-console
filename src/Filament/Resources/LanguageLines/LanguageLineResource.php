<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\LanguageLines;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages\CreateLanguageLine;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages\EditLanguageLine;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages\ListLanguageLines;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages\ViewLanguageLine;
use Misaf\VendraLanguage\Filament\Clusters\Resources\LanguageLines\Schemas\LanguageLineForm;
use Misaf\VendraLanguage\Filament\Clusters\Resources\LanguageLines\Schemas\LanguageLineInfolist;
use Misaf\VendraLanguage\Filament\Clusters\Resources\LanguageLines\Tables\LanguageLineTable;
use Misaf\VendraLanguage\Models\LanguageLine;
use Misaf\VendraSupport\Tenancy\TenantAwareness;

final class LanguageLineResource extends Resource
{
    protected static ?string $model = LanguageLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $slug = 'language-lines';

    public static function getModelLabel(): string
    {
        return __('vendra-language::navigation.language_line');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-language::navigation.language_lines');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-language::navigation.language_lines');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function form(Schema $schema): Schema
    {
        return LanguageLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LanguageLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LanguageLineTable::configure($table);
    }

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['namespace', 'group', 'key'];
    }

    public static function getEloquentQuery(): Builder
    {
        return TenantAwareness::constrainToCurrentTenant(parent::getEloquentQuery());
    }

    /** Console access is the panel gate; `LanguageLine`'s policy checks store permissions. */
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
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLanguageLines::route('/'),
            'create' => CreateLanguageLine::route('/create'),
            'view' => ViewLanguageLine::route('/{record}'),
            'edit' => EditLanguageLine::route('/{record}/edit'),
        ];
    }
}
