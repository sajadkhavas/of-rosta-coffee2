<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }
}
