<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\CreateReseller;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ListResellers;
use Misaf\VendraConsole\Filament\Resources\Resellers\Pages\ViewReseller;
use Misaf\VendraConsole\Filament\Resources\Resellers\Schemas\ResellerForm;
use Misaf\VendraConsole\Filament\Resources\Resellers\Schemas\ResellerInfolist;
use Misaf\VendraConsole\Filament\Resources\Resellers\Tables\ResellerTable;
use Misaf\VendraConsole\Filament\Resources\Resellers\Widgets\ResellerSubscriptionOverview;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraSubscription\Models\Subscription;
use Misaf\VendraSupport\Enums\PlanFeature;

final class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $slug = 'resellers';

    public static function getModelLabel(): string
    {
        return __('vendra-console::navigation.reseller');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendra-console::navigation.resellers');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendra-console::navigation.resellers');
    }

    public static function getNavigationGroup(): string
    {
        return __('vendra-console::navigation.navigation_group_resellers');
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return ResellerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ResellerInfolist::configure($schema);
    }

    /**
     * Include offboarded records, so their view page resolves.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('stores')
            ->withExists(['subscriptions as has_priority_support' => self::activeWithPrioritySupport(...)])
            ->with([
                'user',
                'subscriptions' => fn (Relation $relation): Relation => $relation
                    ->with('plan')
                    ->latest('starts_at'),
            ]);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    private static function activeWithPrioritySupport(Builder $query): Builder
    {
        return $query->active()->onPlanWithFeature(PlanFeature::PrioritySupport->value);
    }

    public static function table(Table $table): Table
    {
        return ResellerTable::configure($table);
    }

    /**
     * Title the reseller by its user, since it has no name of its own.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Reseller ? $record->displayName() : parent::getRecordTitle($record);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user.username', 'user.email'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $reseller = self::reseller($record);

        return [
            __('vendra-console::attributes.email') => $reseller->user->email,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ResellerSubscriptionOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResellers::route('/'),
            'create' => CreateReseller::route('/create'),
            'view' => ViewReseller::route('/{record}'),
        ];
    }

    private static function reseller(Model $record): Reseller
    {
        throw_unless($record instanceof Reseller, InvalidArgumentException::class, 'Reseller resources require a Reseller record.');

        return $record;
    }
}
