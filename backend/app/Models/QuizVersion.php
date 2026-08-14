<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $version
 * @property string $status
 * @property string $title
 * @property array<int, array<string, mixed>> $questions
 * @property array<string, mixed> $scoring_profile
 * @property array<string, mixed> $recommendation_rules
 * @property string $checksum
 * @property string $created_by
 * @property string|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class QuizVersion extends Model
{
    use HasUlids;

    protected $fillable = ['version', 'status', 'title', 'questions', 'scoring_profile', 'recommendation_rules', 'checksum', 'created_by', 'published_by', 'published_at', 'archived_at'];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'questions' => 'array', 'scoring_profile' => 'array', 'recommendation_rules' => 'array',
            'published_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime',
        ];
    }
}
