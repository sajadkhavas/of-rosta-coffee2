<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuizAttempt extends Model
{
    use HasUlids;

    protected $fillable = ['quiz_version_id', 'user_id', 'guest_token_hash', 'submission_key_hash', 'sync_key_hash', 'answers', 'score_profile', 'completed_at', 'synced_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'score_profile' => 'array', 'completed_at' => 'immutable_datetime', 'synced_at' => 'immutable_datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
