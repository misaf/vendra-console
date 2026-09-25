<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\LanguageLines\Pages;

use Filament\Resources\Pages\CreateRecord;
use Misaf\VendraConsole\Filament\Resources\LanguageLines\LanguageLineResource;

final class CreateLanguageLine extends CreateRecord
{
    protected static string $resource = LanguageLineResource::class;

    public function getBreadcrumb(): string
    {
        return self::$breadcrumb ?? __('filament-panels::resources/pages/create-record.breadcrumb').' '.__('vendra-language::navigation.language');
    }
}
