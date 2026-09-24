<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\RelationManagers;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\AddAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ChangeAdministratorEmailTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\ChangeAdministratorPasswordTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\Concerns\InteractsWithAdministratorRecord;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\DemoteAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\DisableAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\EnableAdministratorTableAction;
use Misaf\VendraConsole\Filament\Resources\Stores\Actions\RemoveAdministratorTableAction;
use Misaf\VendraSupport\Filament\Tables\Columns\CreatedAtColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\IsActiveIconColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\RowIndexColumn;
use Misaf\VendraSupport\Filament\Tables\Columns\UpdatedAtColumn;
use Misaf\VendraUser\Filament\Tables\Columns\EmailColumn;
use Misaf\VendraUser\Filament\Tables\Columns\EmailVerifiedAtColumn;
use Misaf\VendraUser\Filament\Tables\Columns\UsernameColumn;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\EmailConstraint;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\EmailVerifiedAtConstraint;
use Misaf\VendraUser\Filament\Tables\Filters\QueryBuilder\Constraints\UsernameConstraint;
use Misaf\VendraUser\Models\User;

final class AdministratorsRelationManager extends RelationManager
{
    use InteractsWithAdministratorRecord;

    protected static string $relationship = 'users';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('vendra-console::attributes.store_administrators');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->onlyAdministrators($query))
            ->columns([
                RowIndexColumn::make(),

                UsernameColumn::make(),

                EmailColumn::make(),

                EmailVerifiedAtColumn::make(),

                IsActiveIconColumn::make()
                    ->state(fn (User $record): bool => ! $record->trashed()),

                CreatedAtColumn::make(),

                UpdatedAtColumn::make(),
            ])
            ->filters(
                [
                    TrashedFilter::make()->default(true),
                    QueryBuilder::make()
                        ->constraints([
                            UsernameConstraint::make(),
                            EmailConstraint::make(),
                            EmailVerifiedAtConstraint::make(),
                        ]),
                ],
                layout: FiltersLayout::AboveContentCollapsible,
            )
            ->headerActions([AddAdministratorTableAction::make()])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        ChangeAdministratorPasswordTableAction::make(),
                        ChangeAdministratorEmailTableAction::make(),
                    ])->dropdown(false),
                    ActionGroup::make([
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
            ])
            ->defaultSort(column: 'users.id', direction: 'desc');
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function onlyAdministrators(Builder $query): Builder
    {
        return $query->administratorOf(self::administratorStore($this));
    }
}
