<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Misaf\VendraUser\Models\User;

/**
 * Normalize and look up the user the --email and --username options name.
 *
 * The commands keep their own messages and decide what a missing or mismatched
 * user means for them.
 */
trait IdentifiesConsoleUser
{
    private function givenEmail(): ?string
    {
        $email = $this->option('email');

        return is_string($email) ? Str::lower(mb_trim($email)) : null;
    }

    private function givenUsername(): ?string
    {
        $username = $this->option('username');

        return is_string($username) ? mb_trim($username) : null;
    }

    /**
     * Both supplied identifiers must resolve to the same user.
     *
     * @return array{user: ?User, mismatched: bool}
     */
    private function findIdentifiedUser(?string $email, ?string $username): array
    {
        $users = User::query()->tenantless()
            ->where(function (Builder $query) use ($email, $username): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }

                if ($username !== null) {
                    $query->orWhere('username', $username);
                }
            })
            // Keep matching consistent with the database's collation.
            ->selectRaw('users.*, email = ? AND username = ? AS matches_identifiers', [$email, $username])
            ->get();

        $user = $users->first();
        $mismatched = $email !== null && $username !== null && $user !== null
            && ($users->count() !== 1 || ! $user->getAttribute('matches_identifiers'));

        $user?->offsetUnset('matches_identifiers');

        return [
            'user' => $user,
            'mismatched' => $mismatched,
        ];
    }
}
