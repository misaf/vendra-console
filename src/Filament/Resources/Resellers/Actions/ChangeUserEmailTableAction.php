<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Actions\UpdateResellerUserEmailAction;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Models\User;

final class ChangeUserEmailTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'changeUserEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('console.change_user_email'))->icon(Heroicon::OutlinedEnvelope)
            ->disabled(fn (Reseller $record): bool => $record->user() === null)
            ->tooltip(fn (Reseller $record): ?string => $record->user() === null ? __('console.user_account_required') : null)
            ->fillForm(fn (Reseller $record): array => ['email' => self::currentUser($record)?->email])
            ->schema([TextInput::make('email')->label(__('console.email'))->email()->required()])
            ->action(function (Reseller $record, array $data): void {
                $user = self::currentUser($record);
                if ($user instanceof User) {
                    resolve(UpdateResellerUserEmailAction::class)->execute($record, $user, (string) Arr::get($data, 'email'));
                    self::notifySuccess(__('console.user_email_updated'));
                }
            });
    }
}
