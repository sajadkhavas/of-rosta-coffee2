<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Inquiry extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'mobile',
        'email',
        'order_number',
        'message',
        'status',
        'ip_hmac',
        'user_agent_hash',
        'duplicate_hash',
        'deduplication_key',
        'assigned_to',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'mobile' => 'encrypted',
            'email' => 'encrypted',
            'message' => 'encrypted',
            'status' => InquiryStatus::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
