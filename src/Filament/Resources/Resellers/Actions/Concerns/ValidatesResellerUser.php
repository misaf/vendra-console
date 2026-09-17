<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Misaf\LaravelEmailVerification\Rules\EmailValidation;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Models\User;

/**
 * Validation for a reseller's main account, which is always a platform user.
 *
 * Uniqueness mirrors the users table indexes: with tenancy enabled, platform
 * users are unique only among other live platform users, so a store's own
 * users never block a reseller username or email. Without tenancy the
 * username index covers every row, trashed ones included.
 */
trait ValidatesResellerUser
{
    /**
     * @return list<mixed>
     */
    protected static function resellerUsernameRules(): array
    {
        return ['alpha_dash', self::platformUserUniqueRule('username')];
    }

    /**
     * @return list<mixed>
     */
    protected static function resellerEmailRules(?int $ignoreUserId = null): array
    {
        return [
            'bail',
            'email:rfc,strict,spoof,filter,filter_unicode',
            new EmailValidation,
            self::platformUserUniqueRule('email', $ignoreUserId),
        ];
    }

    private static function platformUserUniqueRule(string $column, ?int $ignoreUserId = null): Unique
    {
        $rule = Rule::unique(User::class, $column)->ignore($ignoreUserId);

        if (TenantSchema::enabled()) {
            return $rule->whereNull(TenantSchema::column())->withoutTrashed();
        }

        return $column === 'email' ? $rule->withoutTrashed() : $rule;
    }
}
