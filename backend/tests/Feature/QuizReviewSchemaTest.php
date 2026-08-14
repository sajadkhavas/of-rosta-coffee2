<?php

namespace Tests\Feature;

use App\Models\QuizVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QuizReviewSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ps5a_schema_migrates_cleanly_and_has_one_published_seed_version(): void
    {
        foreach ([
            'quiz_versions',
            'quiz_attempts',
            'review_replies',
            'review_reply_revisions',
            'review_reports',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing PS5A table: {$table}");
        }

        $published = QuizVersion::query()->where('status', 'published')->get();

        $this->assertCount(1, $published);
        $this->assertSame(1, $published->firstOrFail()->version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $published->firstOrFail()->checksum);
    }
}
