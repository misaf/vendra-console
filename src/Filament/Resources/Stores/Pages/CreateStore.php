<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Stores\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Stores\Schemas\StoreForm;
use Misaf\VendraConsole\Filament\Resources\Stores\StoreResource;
use Misaf\VendraReseller\Models\Reseller;
use Misaf\VendraStore\Filament\Pages\CreateStorePage;
use Misaf\VendraStore\Filament\Schemas\StorefrontConfigurationFields;
use Misaf\VendraSubscription\Contracts\SubscriptionSubscriber;

final class CreateStore extends CreateStorePage
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = StoreResource::class;

    public function hasSkippableSteps(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return __('console.create_florist_storefront');
    }

    public function getSubheading(): string
    {
        return __('console.create_florist_storefront_description');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('console.create_storefront_action'));
    }

    /**
     * @return list<Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->step(__('console.store_details'), __('console.store_details_description'), Heroicon::BuildingStorefront, [
                ...StoreForm::storeFields(),
                StorefrontConfigurationFields::creationToggle(default: true),
            ]),
            $this->step(__('console.storefront_identity'), __('console.storefront_identity_description'), Heroicon::Sparkles, StorefrontConfigurationFields::identityFields(optional: true))
                ->visible(fn(Get $get): bool => true === $get('create_storefront')),
            $this->step(__('console.storefront_contact'), __('console.storefront_contact_description'), Heroicon::Phone, StorefrontConfigurationFields::contactFields(optional: true))
                ->visible(fn(Get $get): bool => true === $get('create_storefront')),
            $this->step(__('console.storefront_location_social'), __('console.storefront_location_social_description'), Heroicon::MapPin, StorefrontConfigurationFields::locationAndSocialFields(optional: true))
                ->visible(fn(Get $get): bool => true === $get('create_storefront')),
        ];
    }

    /**
     * The console picks the billing reseller on the form. Leaving it empty
     * creates a store the platform owns directly.
     *
     * @param array<string, mixed> $data
     */
    protected function resolveOwner(array $data): ?SubscriptionSubscriber
    {
        $resellerId = $data['reseller_id'] ?? null;

        if (null === $resellerId || '' === $resellerId) {
            return null;
        }

        if ( ! is_numeric($resellerId)) {
            throw new InvalidArgumentException('Invalid reseller provided.');
        }

        return Reseller::query()->findOrFail((int) $resellerId);
    }

    /**
     * Every wizard step is laid out the same way, so the shape is written once.
     *
     * @param array<int, Htmlable|string> $schema
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
