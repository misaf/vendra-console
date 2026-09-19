<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Stores\Schemas\StoreForm;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Pages\CreateStorePage;
use Misaf\VendraStore\Filament\Schemas\StorefrontConfigurationFields;
use Misaf\VendraSubscription\Contracts\SubscriptionSubscriber;

final class CreateStore extends CreateStorePage
{
    use HasWizard;

    protected static string $resource = StoreResource::class;

    public function hasSkippableSteps(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return __('vendra-console::attributes.create_florist_storefront');
    }

    public function getSubheading(): string
    {
        return __('vendra-console::attributes.create_florist_storefront_description');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('vendra-console::actions.create_storefront_action'));
    }

    /**
     * @return list<Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->step(__('vendra-console::attributes.store_details'), __('vendra-console::attributes.store_details_description'), Heroicon::BuildingStorefront, [
                ...StoreForm::storeFields(),
                StorefrontConfigurationFields::creationToggle(default: true),
            ]),
            $this->step(__('vendra-console::attributes.storefront_identity'), __('vendra-console::attributes.storefront_identity_description'), Heroicon::Sparkles, StorefrontConfigurationFields::identityFields(optional: true))
                ->visible(fn (Get $get): bool => $get('create_storefront') === true),
            $this->step(__('vendra-console::attributes.storefront_contact'), __('vendra-console::attributes.storefront_contact_description'), Heroicon::Phone, StorefrontConfigurationFields::contactFields(optional: true))
                ->visible(fn (Get $get): bool => $get('create_storefront') === true),
            $this->step(__('vendra-console::attributes.storefront_location_social'), __('vendra-console::attributes.storefront_location_social_description'), Heroicon::MapPin, StorefrontConfigurationFields::locationAndSocialFields(optional: true))
                ->visible(fn (Get $get): bool => $get('create_storefront') === true),
        ];
    }

    /**
     * Use the reseller picked on the form; none means a platform store.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveReseller(array $data): ?SubscriptionSubscriber
    {
        $resellerId = Arr::get($data, 'reseller_id', null);

        if ($resellerId === null || $resellerId === '') {
            return null;
        }

        throw_unless(is_numeric($resellerId), InvalidArgumentException::class, 'Invalid reseller provided.');

        return Reseller::query()->findOrFail((int) $resellerId);
    }

    /**
     * @param  array<int, Htmlable|string>  $schema
     */
    private function step(string $label, string $description, Heroicon $icon, array $schema): Step
    {
        return Step::make($label)
            ->description($description)
            ->icon($icon)
            ->completedIcon(Heroicon::CheckCircle)
            ->schema($schema)
            ->columns(['default' => 1, 'md' => 2]);
    }
}
