<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

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
        static::updating(static function (): never {
            throw new LogicException('Audit records are append-only and cannot be updated.');
        });

        static::deleting(static function (): never {
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
