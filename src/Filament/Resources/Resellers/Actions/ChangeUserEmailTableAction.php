<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\ValidatesResellerUser;
use Misaf\VendraReseller\Actions\UpdateResellerUserEmailAction;
use Misaf\VendraReseller\Models\Reseller;

final class ChangeUserEmailTableAction extends Action
{
    use InteractsWithResellerRecord;
    use ValidatesResellerUser;

    public static function getDefaultName(): string
    {
        return 'changeUserEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_user_email'))->icon(Heroicon::OutlinedEnvelope)
            ->hidden(fn (Reseller $record): bool => $record->trashed())
            ->fillForm(fn (Reseller $record): array => ['email' => $record->user->email])
            ->schema([
                TextInput::make('email')->label(__('vendra-console::attributes.email'))->email()->required()
                    ->rules(fn (Reseller $record): array => self::resellerEmailRules($record->user_id)),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(UpdateResellerUserEmailAction::class)->execute($record, (string) Arr::get($data, 'email'));
                self::notifySuccess(__('vendra-console::messages.user_email_updated'));
            });
    }
}
