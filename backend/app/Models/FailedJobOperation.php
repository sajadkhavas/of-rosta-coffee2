<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $failed_job_uuid
 * @property string $action
 * @property string $status
 * @property string $reason
 * @property string $requested_by_id
 * @property string|null $confirmed_by_id
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $confirmed_at
 */
final class FailedJobOperation extends Model
{
    use HasUlids;

    protected $fillable = [
        'failed_job_uuid',
        'action',
        'status',
        'reason',
        'requested_by_id',
        'confirmed_by_id',
        'requested_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }
}
