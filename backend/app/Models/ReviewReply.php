<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $review_id
 * @property string $roastery_id
 * @property string $author_id
 * @property string $body
 * @property string $status
 * @property string|null $moderated_by
 * @property CarbonImmutable|null $moderated_at
 * @property string|null $moderation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Review|null $review
 * @property-read User|null $author
 */
final class ReviewReply extends Model
{
    use HasUlids;

    protected $fillable = ['review_id', 'roastery_id', 'author_id', 'body', 'status', 'moderated_by', 'moderated_at', 'moderation_reason'];

    protected function casts(): array
    {
        return ['moderated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<ReviewReplyRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReviewReplyRevision::class, 'reply_id');
    }
}
