<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AddAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ChangeAdministratorEmailTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ChangeAdministratorPasswordTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\DemoteAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\DisableAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\EnableAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\PromoteAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RemoveAdministratorTableAction;
use Misaf\VendraUser\Models\User;

final class AdministratorsRelationManager extends RelationManager
{
    use InteractsWithAdministratorRecord;

    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('vendra-console::attributes.store_administrators');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->afterQuery(fn (Collection $users): Collection => self::loadAdministratorRoles(self::administratorStore($this), $users)))
            ->columns([
                TextColumn::make('username')
                    ->label(__('vendra-console::attributes.username'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('vendra-console::attributes.email'))
                    ->searchable(),

                IconColumn::make('administrator')
                    ->label(__('vendra-console::attributes.administrator'))
                    ->boolean()
                    ->state(fn (User $record): bool => self::isAdministrator(self::administratorStore($this), $record)),

                IconColumn::make('enabled')
                    ->label(__('vendra-console::attributes.enabled'))
                    ->boolean()
                    ->state(fn (User $record): bool => ! $record->trashed()),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([AddAdministratorTableAction::make()])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        ChangeAdministratorPasswordTableAction::make(),
                        ChangeAdministratorEmailTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        PromoteAdministratorTableAction::make(),
                        DemoteAdministratorTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        DisableAdministratorTableAction::make(),
                        EnableAdministratorTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        RemoveAdministratorTableAction::make(),
                    ])->dropdown(false),
                ]),
            ]);
    }
}
