<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'is_active', 'preferred_locale', 'newsrooms',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'newsrooms' => 'array',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function author(): HasOne
    {
        return $this->hasOne(Author::class);
    }

    /** Permission keys granted through the user's role (cached per request). */
    public function permissionKeys(): array
    {
        static $cache = [];

        if (! $this->role_id) {
            return [];
        }

        return $cache[$this->role_id] ??= $this->role?->permissions()->pluck('key')->all() ?? [];
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($key, $this->permissionKeys(), true);
    }

    /** Editions (en|so) this person may work in. Super admins and unassigned users: both. */
    public function newsroomEditions(): array
    {
        if ($this->isSuperAdmin() || empty($this->newsrooms)) {
            return array_keys(editions());
        }

        return array_values(array_intersect(array_keys(editions()), $this->newsrooms));
    }

    public function worksIn(string $edition): bool
    {
        return in_array($edition, $this->newsroomEditions(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->key === 'super-admin';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function requiresTwoFactor(): bool
    {
        return config('doogsan.enforce_2fa') && (bool) $this->role?->requires_2fa;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }
}
