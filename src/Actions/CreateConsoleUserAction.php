<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Actions\CreateUserAction;
use Misaf\VendraUser\Models\User;

final readonly class CreateConsoleUserAction
{
    public function __construct(private CreateUserAction $createUserAction) {}

    /**
     * Create a platform-level user and its console grant together.
     *
     * The caller validates and normalizes the email; the users table's unique
     * guard still rejects an address another platform user already holds.
     */
    public function execute(string $email, string $password): User
    {
        return DB::transaction(function () use ($email, $password): User {
            $user = $this->createUserAction->execute(
                tenant: null,
                username: self::usernameFor($email),
                email: $email,
                password: $password,
            );

            ConsoleUser::query()->create(['user_id' => $user->getKey()]);

            return $user;
        });
    }

    /**
     * Usernames are unique among platform users, so a local part already
     * taken (another console or reseller user) gets a numeric suffix. Every
     * row is checked, trashed and tenant-scoped ones included, because which
     * unique index applies depends on whether tenancy is enabled.
     */
    private static function usernameFor(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->slug('_')
            ->substr(0, 12)
            ->toString();

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;

        for ($suffix = 2; DB::table('users')->where('username', $candidate)->exists(); $suffix++) {
            $candidate = Str::substr($base, 0, 12 - mb_strlen("_{$suffix}")).'_'.$suffix;
        }

        return $candidate;
    }
}
