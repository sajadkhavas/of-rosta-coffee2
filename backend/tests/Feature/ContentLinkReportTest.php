<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ContentAuthor;
use App\Models\ContentEntry;
use App\Models\ContentRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class ContentLinkReportTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_administrator_receives_orphan_weak_and_broken_link_findings(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $author = ContentAuthor::query()->create([
            'name' => 'تحریریه گزارش',
            'slug' => 'report-editorial',
            'bio' => null,
            'credentials' => [],
            'is_active' => true,
        ]);

        $source = $this->entry($author, 'source-guide');
        $target = $this->entry($author, 'target-guide');

        ContentRelation::query()->create([
            'content_entry_id' => $source->id,
            'relation_type' => 'related',
            'target_type' => 'content',
            'target_key' => $target->slug,
            'anchor_text' => 'راهنمای مقصد',
            'position' => 0,
        ]);
        ContentRelation::query()->create([
            'content_entry_id' => $source->id,
            'relation_type' => 'recommends',
            'target_type' => 'product',
            'target_key' => 'deleted-product',
            'anchor_text' => 'محصول حذف‌شده',
            'position' => 1,
        ]);

        $this->getJson('/api/v1/admin/content-link-report')
            ->assertOk()
            ->assertJsonPath('data.summary.entries_by_status.published', 2)
            ->assertJsonPath('data.summary.total_relations_scanned', 2)
            ->assertJsonPath('data.summary.broken_relations', 1)
            ->assertJsonPath('data.summary.orphaned_entries', 1)
            ->assertJsonPath('data.summary.weak_outbound_entries', 1)
            ->assertJsonPath('data.broken_relations.0.target_key', 'deleted-product')
            ->assertJsonPath('data.broken_relations.0.reason', 'missing_target')
            ->assertJsonPath('data.orphaned_entries.0.slug', 'source-guide')
            ->assertJsonPath('data.weak_outbound_entries.0.slug', 'target-guide')
            ->assertJsonPath('data.weak_outbound_entries.0.relations_count', 0);
    }

    public function test_non_administrator_cannot_read_link_report(): void
    {
        $customer = User::factory()->create();
        $this->authenticateWithRole($customer, Role::Customer);

        $this->getJson('/api/v1/admin/content-link-report')->assertForbidden();
    }

    private function entry(ContentAuthor $author, string $slug): ContentEntry
    {
        return ContentEntry::query()->create([
            'author_id' => $author->id,
            'type' => 'guide',
            'title' => $slug,
            'slug' => $slug,
            'canonical_path' => '/guides/'.$slug,
            'excerpt' => 'محتوای تست گزارش لینک داخلی.',
            'body' => [
                ['type' => 'paragraph', 'text' => 'پاراگراف اول.'],
                ['type' => 'paragraph', 'text' => 'پاراگراف دوم.'],
            ],
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'seo_title' => $slug,
            'seo_description' => 'توضیح سئو تست گزارش.',
            'robots_index' => true,
            'robots_follow' => true,
            'schema_type' => 'BlogPosting',
            'keywords' => [],
            'content_hash' => hash('sha256', $slug),
        ]);
    }
}
