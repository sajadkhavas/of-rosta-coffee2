<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;

    protected $fillable = [
        'mobile',
        'name',
        'email',
        'mobile_verified_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
        ];
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function stockLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class, 'actor_id');
    }

    public function checkoutQuotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderIdempotencyKeys(): HasMany
    {
        return $this->hasMany(OrderIdempotencyKey::class);
    }

    public function roleNames(): array
    {
        return $this->roleAssignments
            ->pluck('role')
            ->map(static fn (Role|string $role): string => $role instanceof Role ? $role->value : $role)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function hasRole(
        Role $role,
        string $scopeType = 'global',
        string $scopeId = 'global',
    ): bool {
        return $this->roleAssignments()
            ->where('role', $role->value)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->exists();
    }
}
