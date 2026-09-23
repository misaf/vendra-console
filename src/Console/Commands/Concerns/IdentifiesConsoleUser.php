<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Misaf\VendraUser\Models\User;

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
     * @return array{user: ?User, mismatched: bool, unmatched: 'email'|'username'|null}
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
            ->selectRaw('users.*, email = ? AS matches_email, username = ? AS matches_username', [$email, $username])
            ->get();

        $emailUser = $email === null ? null : $users->first(fn (User $user): bool => (bool) $user->getAttribute('matches_email'));
        $usernameUser = $username === null ? null : $users->first(fn (User $user): bool => (bool) $user->getAttribute('matches_username'));

        $users->each(function (User $user): void {
            $user->offsetUnset('matches_email');
            $user->offsetUnset('matches_username');
        });

        $unmatched = match (true) {
            $email !== null && $emailUser === null => 'email',
            $username !== null && $usernameUser === null => 'username',
            default => null,
        };

        $mismatched = $emailUser !== null && $usernameUser !== null && ! $emailUser->is($usernameUser);

        return [
            'user' => $unmatched === null && ! $mismatched ? $emailUser ?? $usernameUser : null,
            'mismatched' => $mismatched,
            'unmatched' => $unmatched,
        ];
    }
}
