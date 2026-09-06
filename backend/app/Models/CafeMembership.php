<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $cafe_id
 * @property string $user_id
 * @property string $role
 * @property bool $is_active
 * @property string|null $created_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Cafe|null $cafe
 * @property-read User|null $user
 * @property-read User|null $createdBy
 */
final class CafeMembership extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    protected $fillable = [
        'cafe_id',
        'user_id',
        'role',
        'is_active',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Cafe, $this> */
    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
