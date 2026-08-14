<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReviewReply extends Model
{
    use HasUlids;

    protected $fillable = ['review_id', 'roastery_id', 'author_id', 'body', 'status', 'moderated_by', 'moderated_at', 'moderation_reason'];

    protected function casts(): array
    {
        return ['moderated_at' => 'immutable_datetime'];
    }

    public function review(): BelongsTo { return $this->belongsTo(Review::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function revisions(): HasMany { return $this->hasMany(ReviewReplyRevision::class, 'reply_id'); }
}
