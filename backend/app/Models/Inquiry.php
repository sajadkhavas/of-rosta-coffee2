<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $type
 * @property string|null $name
 * @property string|null $mobile
 * @property string|null $email
 * @property string|null $order_number
 * @property string|null $message
 * @property InquiryStatus $status
 * @property string|null $ip_hmac
 * @property string|null $user_agent_hash
 * @property string|null $duplicate_hash
 * @property string|null $deduplication_key
 * @property string|null $assigned_to
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $assignee
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
