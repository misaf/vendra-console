<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

use function Laravel\Prompts\search;

trait IdentifiesConsoleUser
{
    private const int USER_SEARCH_LIMIT = 10;

    /**
     * Search for the user by email or username when an interactive run names neither.
     *
     * The chosen email is written back into --email, so it is validated like the option.
     * With no user to choose from, the run fails with the given message instead.
     */
    private function searchForMissingUser(string $label, bool $withConsoleAccess, string $noUserMessage): bool
    {
        if (! $this->input->isInteractive() || $this->option('email') !== null || $this->option('username') !== null) {
            return true;
        }

        if ($this->searchUsers('', $withConsoleAccess) === []) {
            $this->components->error($noUserMessage);

            return false;
        }

        $this->input->setOption('email', search(
            label: $label,
            placeholder: 'Search by email or username',
            options: fn (string $value): array => $this->searchUsers($value, $withConsoleAccess),
            scroll: self::USER_SEARCH_LIMIT,
        ));

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function searchUsers(string $value, bool $withConsoleAccess): array
    {
        $activeConsoleUserIds = Console::query()->active()->select('user_id');

        return User::query()
            ->tenantless()
            ->when(
                $withConsoleAccess,
                fn (Builder $query): Builder => $query->whereIn('id', $activeConsoleUserIds),
                fn (Builder $query): Builder => $query->whereNotIn('id', $activeConsoleUserIds),
            )
            ->when(mb_trim($value) !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereLike('email', '%'.mb_trim($value).'%')
                    ->orWhereLike('username', '%'.mb_trim($value).'%'),
            ))
            ->orderBy('email')
            ->limit(self::USER_SEARCH_LIMIT)
            ->get(['email', 'username'])
            ->mapWithKeys(fn (User $user): array => [$user->email => "{$user->email} ({$user->username})"])
            ->all();
    }

    private function givenEmail(): ?string
    {
        $email = $this->option('email');

        return is_string($email) ? $this->normalizeEmail($email) : null;
    }

    private function givenUsername(): ?string
    {
        $username = $this->option('username');

        return is_string($username) ? $this->normalizeUsername($username) : null;
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(mb_trim($email));
    }

    private function normalizeUsername(string $username): string
    {
        return mb_trim($username);
    }
}
