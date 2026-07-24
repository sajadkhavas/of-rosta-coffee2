<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property Role $role
 * @property string|null $scope_type
 * @property string|null $scope_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
final class UserRole extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'role',
        'scope_type',
        'scope_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
