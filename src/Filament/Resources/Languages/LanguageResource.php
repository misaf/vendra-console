<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Languages;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\Languages\Pages\CreateLanguage;
use Misaf\VendraConsole\Filament\Resources\Languages\Pages\EditLanguage;
use Misaf\VendraConsole\Filament\Resources\Languages\Pages\ListLanguages;
use Misaf\VendraConsole\Filament\Resources\Languages\Pages\ViewLanguage;
use Misaf\VendraLanguage\Filament\Clusters\Resources\Languages\Schemas\LanguageForm;
use Misaf\VendraLanguage\Filament\Clusters\Resources\Languages\Schemas\LanguageInfolist;
use Misaf\VendraLanguage\Filament\Clusters\Resources\Languages\Tables\LanguageTable;
use Misaf\VendraLanguage\Models\Language;
use Misaf\VendraSupport\Tenancy\TenantAwareness;

final class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $recordTitleAttribute = 'locale';

    protected static ?string $slug = 'languages';

    public static function getModelLabel(): string
    {
        return __('vendra-language::navigation.language');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-language::navigation.languages');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-language::navigation.languages');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.platform_settings');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return LanguageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LanguageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LanguageTable::configure($table, self::class);
    }

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['locale'];
    }

    public static function getEloquentQuery(): Builder
    {
        return TenantAwareness::constrainToCurrentTenant(parent::getEloquentQuery());
    }

    /** Console access is the panel gate; `Language`'s policy checks store permissions. */
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

    public static function canReorder(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLanguages::route('/'),
            'create' => CreateLanguage::route('/create'),
            'view' => ViewLanguage::route('/{record}'),
            'edit' => EditLanguage::route('/{record}/edit'),
        ];
    }
}
