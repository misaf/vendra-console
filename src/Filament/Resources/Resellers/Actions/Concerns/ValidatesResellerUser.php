<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Misaf\LaravelEmailVerification\Rules\EmailValidation;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Models\User;

/**
 * Uniqueness mirrors the users table indexes, which depend on whether tenancy is enabled.
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
