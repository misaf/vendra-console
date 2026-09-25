<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Support;

use Misaf\VendraConsole\Settings\BillingSettings;
use Misaf\VendraConsole\Settings\ConsoleSettings;
use Misaf\VendraSubscription\Contracts\BillingProfile;

final readonly class SettingsBillingProfile implements BillingProfile
{
    public function taxRate(): int
    {
        return resolve(BillingSettings::class)->tax_rate;
    }

    public function taxLabel(): string
    {
        return resolve(BillingSettings::class)->tax_label;
    }

    public function seller(): array
    {
        $settings = resolve(BillingSettings::class);

        return [
            'name' => $settings->seller_name ?? resolve(ConsoleSettings::class)->brand_name,
            'address' => $settings->seller_address,
            'tax_id' => $settings->seller_tax_id,
        ];
    }
}
