<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewReport extends Model
{
    use HasUlids;
    public const REASONS = ['spam', 'harassment', 'hate', 'personal_data', 'fraud', 'off_topic', 'other'];
    public const STATUSES = ['open', 'reviewing', 'resolved', 'dismissed'];
    protected $fillable = ['review_id', 'user_id', 'reason', 'evidence', 'status', 'moderated_by', 'moderated_at', 'resolution_reason'];
    protected function casts(): array { return ['moderated_at' => 'immutable_datetime']; }
    public function review(): BelongsTo { return $this->belongsTo(Review::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
