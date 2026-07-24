<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $actor_id
 * @property string|null $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property string|null $request_id
 * @property string|null $ip_hash
 * @property array<mixed> $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $actor
 * @property-read Model|null $auditable
 */
final class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'request_id',
        'ip_hash',
        'metadata',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Audit records are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Audit records are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'encrypted:array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
