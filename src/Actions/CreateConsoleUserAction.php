<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Actions\CreateUserAction;
use Misaf\VendraUser\Models\User;

final readonly class CreateConsoleUserAction
{
    private const int MAX_USERNAME_ATTEMPTS = 5;

    public function __construct(private CreateUserAction $createUserAction) {}

    /**
     * A concurrent create can claim the chosen username between the check and
     * the insert; that attempt is rolled back and retried with a fresh suffix.
     * Any other unique violation, such as a taken email, is rethrown.
     *
     * @throws UniqueConstraintViolationException
     */
    public function execute(string $email, string $password): User
    {
        for ($attempt = 1; ; $attempt++) {
            $username = self::usernameFor($email);

            try {
                return DB::transaction(function () use ($username, $email, $password): User {
                    $user = $this->createUserAction->execute(
                        tenant: null,
                        username: $username,
                        email: $email,
                        password: $password,
                    );

                    ConsoleUser::query()->create(['user_id' => $user->getKey()]);

                    return $user;
                });
            } catch (UniqueConstraintViolationException $exception) {
                $usernameWasTaken = DB::table('users')->where('username', $username)->exists();

                throw_if(! $usernameWasTaken || $attempt >= self::MAX_USERNAME_ATTEMPTS, $exception);
            }
        }
    }

    /**
     * Usernames are unique among platform users, so a taken local part gets a
     * numeric suffix. Every row is checked, trashed and tenant-scoped ones
     * included, because which unique index applies depends on whether tenancy
     * is enabled.
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
