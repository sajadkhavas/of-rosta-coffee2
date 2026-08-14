<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $review_id
 * @property string $user_id
 * @property string $reason
 * @property string|null $evidence
 * @property string $status
 * @property string|null $moderated_by
 * @property CarbonImmutable|null $moderated_at
 * @property string|null $resolution_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Review|null $review
 * @property-read User|null $user
 */
final class ReviewReport extends Model
{
    use HasUlids;
    public const REASONS = ['spam', 'harassment', 'hate', 'personal_data', 'fraud', 'off_topic', 'other'];
    public const STATUSES = ['open', 'reviewing', 'resolved', 'dismissed'];
    protected $fillable = ['review_id', 'user_id', 'reason', 'evidence', 'status', 'moderated_by', 'moderated_at', 'resolution_reason'];
    protected function casts(): array { return ['moderated_at' => 'immutable_datetime']; }
    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo { return $this->belongsTo(Review::class); }
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
