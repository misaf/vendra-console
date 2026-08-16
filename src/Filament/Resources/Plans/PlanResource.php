<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Plans;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\CreatePlan;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\EditPlan;
use Misaf\VendraConsole\Filament\Resources\Plans\Pages\ListPlans;
use Misaf\VendraConsole\Filament\Resources\Plans\Schemas\PlanForm;
use Misaf\VendraConsole\Filament\Resources\Plans\Tables\PlanTable;
use Misaf\VendraSubscription\Models\Plan;

final class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'plans';

    public static function getModelLabel(): string
    {
        return __('console.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.plans');
    }

    public static function getNavigationGroup(): string
    {
        return __('console.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanTable::configure($table);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $plan = self::plan($record);

        return [
            __('console.period') => "{$plan->period_count} " . __("console.period_{$plan->period_unit->value}"),
            __('console.price')  => $plan->isFree() ? __('console.free') : $plan->formattedPrice(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit'   => EditPlan::route('/{record}/edit'),
        ];
    }

    private static function plan(Model $record): Plan
    {
        if ( ! $record instanceof Plan) {
            throw new InvalidArgumentException('Plan resources require a Plan record.');
        }

        return $record;
    }
}
