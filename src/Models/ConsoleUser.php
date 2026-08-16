<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Database\Factories\ConsoleUserFactory;

#[Fillable(['username', 'email', 'email_verified_at', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(ConsoleUserFactory::class)]
final class ConsoleUser extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<ConsoleUserFactory> */
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return 'console' === $panel->getId();
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn(string $value): string => Str::lower(mb_trim($value)),
        );
    }
}
