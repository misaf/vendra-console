<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Misaf\VendraConsole\Database\Factories\ConsoleUserFactory;
use Misaf\VendraUser\Models\User;

/**
 * A console panel grant, not an identity.
 *
 * A row gives the canonical `User` access to the console panel; deleting it
 * revokes access while the identity survives. Never authenticate against this
 * model — the `console` guard resolves the canonical user. Grants are written
 * through `Actions\CreateConsoleUserAction` and `Actions\GrantConsoleAccessAction`,
 * and removed through `Actions\RevokeConsoleUserAction`.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id'])]
#[UseFactory(ConsoleUserFactory::class)]
final class ConsoleUser extends Model
{
    /** @use HasFactory<ConsoleUserFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
