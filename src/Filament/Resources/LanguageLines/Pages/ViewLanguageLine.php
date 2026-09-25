<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\LanguageLineResource;

final class ViewLanguageLine extends ViewRecord
{
    protected static string $resource = LanguageLineResource::class;

    public function getBreadcrumb(): string
    {
        return self::$breadcrumb ?? __('filament-panels::resources/pages/view-record.breadcrumb').' '.__('vendra-language::navigation.language');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
