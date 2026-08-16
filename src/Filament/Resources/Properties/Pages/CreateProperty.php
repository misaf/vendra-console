<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Properties\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Misaf\VendraConsole\Filament\Resources\Properties\PropertyResource;
use Misaf\VendraConsole\Filament\Resources\Properties\Schemas\PropertyForm;
use Misaf\VendraProperty\Filament\Pages\CreatePropertyPage;
use Misaf\VendraProperty\Filament\Schemas\StorefrontConfigurationFields;
use Misaf\VendraReseller\Models\Reseller;

final class CreateProperty extends CreatePropertyPage
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PropertyResource::class;

    public function hasSkippableSteps(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return __('console.create_florist_storefront');
    }

    public function getSubheading(): ?string
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
            $this->step(__('console.property_details'), __('console.property_details_description'), Heroicon::BuildingStorefront, [
                ...PropertyForm::propertyFields(),
                PropertyForm::activeField(),
            ]),
            $this->step(__('console.storefront_identity'), __('console.storefront_identity_description'), Heroicon::Sparkles, StorefrontConfigurationFields::identityFields(optional: false)),
            $this->step(__('console.storefront_contact'), __('console.storefront_contact_description'), Heroicon::Phone, StorefrontConfigurationFields::contactFields(optional: false)),
            $this->step(__('console.storefront_location_social'), __('console.storefront_location_social_description'), Heroicon::MapPin, StorefrontConfigurationFields::locationAndSocialFields(optional: false)),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function resolveReseller(array $data): Reseller
    {
        $resellerId = $data['reseller_id'] ?? null;

        if ( ! is_numeric($resellerId)) {
            throw new InvalidArgumentException('Invalid reseller provided.');
        }

        return Reseller::query()->findOrFail((int) $resellerId);
    }

    /**
     * Every wizard step is laid out the same way, so the shape is written once.
     *
     * @param array<int, mixed> $schema
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
