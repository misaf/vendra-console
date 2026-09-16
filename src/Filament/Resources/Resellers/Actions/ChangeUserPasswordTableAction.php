<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Forms\Components\NewPasswordInput;
use Misaf\VendraConsole\Filament\Forms\Components\PasswordConfirmationInput;
use Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns\InteractsWithResellerRecord;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;

final class ChangeUserPasswordTableAction extends Action
{
    use InteractsWithResellerRecord;

    public static function getDefaultName(): string
    {
        return 'changeUserPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('vendra-console::actions.change_user_password'))->icon(Heroicon::OutlinedKey)
            ->disabled(fn (Reseller $record): bool => $record->user() === null)
            ->tooltip(fn (Reseller $record): ?string => $record->user() === null ? __('vendra-console::messages.user_account_required') : null)
            ->schema([
                NewPasswordInput::make(),
                PasswordConfirmationInput::make(),
            ])
            ->action(function (Reseller $record, array $data): void {
                resolve(UpdateUserPasswordAction::class)->execute(
                    $record->user() ?? throw new InvalidArgumentException('Reseller user account is required.'),
                    (string) Arr::get($data, 'password'),
                );
                self::notifySuccess(__('vendra-console::messages.user_password_updated'));
            });
    }
}
