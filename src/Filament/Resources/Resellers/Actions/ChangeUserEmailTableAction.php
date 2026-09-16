<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
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
            ->label(__('vendra-console::actions.change_user_email'))->icon(Heroicon::OutlinedEnvelope)
            ->disabled(fn (Reseller $record): bool => $record->user() === null)
            ->tooltip(fn (Reseller $record): ?string => $record->user() === null ? __('vendra-console::messages.user_account_required') : null)
            ->fillForm(fn (Reseller $record): array => ['email' => $record->user()?->email])
            ->schema([
                TextInput::make('email')->label(__('vendra-console::attributes.email'))->email()->required()
                    ->rule(fn (Reseller $record): mixed => Rule::unique(User::class, 'email')
                        ->withoutTrashed()
                        ->ignore($record->user()?->getKey())),
            ])
            ->action(function (Reseller $record, array $data): void {
                $user = $record->user();
                if ($user instanceof User) {
                    resolve(UpdateResellerUserEmailAction::class)->execute($record, $user, (string) Arr::get($data, 'email'));
                    self::notifySuccess(__('vendra-console::messages.user_email_updated'));
                }
            });
    }
}
