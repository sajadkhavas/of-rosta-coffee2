<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
