<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Settings;

use Spatie\LaravelSettings\Settings;

final class BillingSettings extends Settings
{
    public ?string $seller_name = null;

    public ?string $seller_address = null;

    public ?string $seller_tax_id = null;

    /**
     * The tax added to every plan charge, in basis points (1900 is 19%).
     */
    public int $tax_rate;

    public string $tax_label;

    public static function group(): string
    {
        return 'billing';
    }

    public static function repository(): string
    {
        return 'global';
    }
}
