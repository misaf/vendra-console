<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Languages\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\LanguageLineResource;
use Misaf\VendraConsole\Filament\Resources\Languages\LanguageResource;
use Misaf\VendraLanguage\Filament\Actions\SyncLanguageLinesPageAction;

final class ListLanguages extends ListRecords
{
    protected static string $resource = LanguageResource::class;

    public function getBreadcrumb(): string
    {
        return self::$breadcrumb ?? __('filament-panels::resources/pages/list-records.breadcrumb').' '.__('vendra-language::navigation.language');
    }

    protected function getHeaderActions(): array
    {
        return [
            SyncLanguageLinesPageAction::make(LanguageLineResource::class),

            CreateAction::make(),
        ];
    }
}
