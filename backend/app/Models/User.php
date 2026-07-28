<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string|null $mobile
 * @property string|null $name
 * @property string|null $email
 * @property CarbonImmutable|null $mobile_verified_at
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, UserRole> $roleAssignments
 * @property-read Collection<int, AuthSession> $authSessions
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, StockLedgerEntry> $stockLedgerEntries
 * @property-read Collection<int, CheckoutQuote> $checkoutQuotes
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, OrderIdempotencyKey> $orderIdempotencyKeys
 * @property-read Collection<int, HubWorkItem> $assignedHubWorkItems
 */
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

    /** @return HasMany<UserRole, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** @return HasMany<AuthSession, $this> */
    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<StockLedgerEntry, $this> */
    public function stockLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class, 'actor_id');
    }

    /** @return HasMany<CheckoutQuote, $this> */
    public function checkoutQuotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<OrderIdempotencyKey, $this> */
    public function orderIdempotencyKeys(): HasMany
    {
        return $this->hasMany(OrderIdempotencyKey::class);
    }

    /** @return HasMany<HubWorkItem, $this> */
    public function assignedHubWorkItems(): HasMany
    {
        return $this->hasMany(HubWorkItem::class, 'assigned_operator_id');
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
