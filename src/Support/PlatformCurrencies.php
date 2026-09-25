<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Support;

use Misaf\VendraCurrency\Models\Currency;

/**
 * The platform's active currencies, which plans are priced and reseller
 * wallets are credited in.
 */
final class PlatformCurrencies
{
    /**
     * Get the options, such as `['USD' => 'US Dollar (USD)']`, keeping codes a
     * record already uses even after their currency was deactivated or removed.
     *
     * @param  list<string|null>  $keep
     * @return array<string, string>
     */
    public static function options(array $keep = []): array
    {
        $options = Currency::query()
            ->platform()
            ->active()
            ->ordered()
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Currency $currency): array => [$currency->code => "{$currency->name} ({$currency->code})"])
            ->all();

        foreach ($keep as $code) {
            if (filled($code) && ! array_key_exists($code, $options)) {
                $options[$code] = $code;
            }
        }

        return $options;
    }

    public static function defaultCode(): ?string
    {
        $code = Currency::query()
            ->platform()
            ->active()
            ->where('is_default', true)
            ->value('code');

        return is_string($code) ? $code : null;
    }
}
