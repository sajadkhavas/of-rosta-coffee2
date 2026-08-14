<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $quiz_version_id
 * @property string|null $user_id
 * @property string|null $guest_token_hash
 * @property string $submission_key_hash
 * @property string|null $sync_key_hash
 * @property array<string, string|array<int, string>> $answers
 * @property array<string, mixed> $score_profile
 * @property CarbonImmutable $completed_at
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read QuizVersion|null $version
 * @property-read User|null $user
 */
final class QuizAttempt extends Model
{
    use HasUlids;

    protected $fillable = ['quiz_version_id', 'user_id', 'guest_token_hash', 'submission_key_hash', 'sync_key_hash', 'answers', 'score_profile', 'completed_at', 'synced_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'score_profile' => 'array', 'completed_at' => 'immutable_datetime', 'synced_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<QuizVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
