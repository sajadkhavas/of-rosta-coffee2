<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ContentAuthor;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class ContentSeoTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_public_content_only_exposes_published_entries_and_indexable_urls(): void
    {
        $author = $this->author();
        $published = $this->entry(
            $author,
            'published-guide',
            '/guides/published-guide',
            'published',
            true,
        );
        $this->entry(
            $author,
            'draft-guide',
            '/guides/draft-guide',
            'draft',
            false,
        );
        $this->entry(
            $author,
            'noindex-guide',
            '/guides/noindex-guide',
            'published',
            false,
        );

        $this->getJson('/api/v1/content/published-guide')
            ->assertOk()
            ->assertJsonPath('data.id', $published->id)
            ->assertJsonPath(
                'data.seo.canonical_path',
                '/guides/published-guide',
            )
            ->assertJsonPath('data.seo.robots_index', true)
            ->assertJsonPath('data.body.0.type', 'paragraph');

        $this->getJson('/api/v1/content/draft-guide')->assertNotFound();

        $this->getJson('/api/v1/seo/indexable')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.path',
                '/guides/published-guide',
            );
    }

    public function test_admin_content_workflow_rejects_raw_blocks_requires_review_and_detects_stale_edits(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);

        $author = $this->postJson('/api/v1/admin/content-authors', [
            'name' => 'نویسنده تست',
            'slug' => 'test-author',
            'bio' => 'نویسنده محتوای تخصصی رستا.',
            'credentials' => ['آموزش قهوه'],
        ])->assertOk()->json('data');

        $base = [
            'author_id' => $author['id'],
            'type' => 'guide',
            'title' => 'راهنمای تست',
            'slug' => 'test-guide',
            'canonical_path' => '/guides/test-guide',
            'excerpt' => 'توضیح منحصربه‌فرد برای تست انتشار محتوای رستا.',
            'body' => [
                [
                    'type' => 'paragraph',
                    'text' => 'پاراگراف اصلی راهنمای تست.',
                ],
                [
                    'type' => 'heading',
                    'level' => 2,
                    'text' => 'بخش دوم',
                ],
            ],
            'seo_title' => 'راهنمای تست | رستا',
            'seo_description' => 'توضیح سئو برای راهنمای تست محتوای رستا.',
            'robots_index' => true,
            'keywords' => ['راهنمای تست'],
        ];

        $this->postJson('/api/v1/admin/content', [
            ...$base,
            'slug' => 'unsafe-guide',
            'canonical_path' => '/guides/unsafe-guide',
            'body' => [[
                'type' => 'html',
                'html' => '<script>alert(1)</script>',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'content.block_invalid');

        $entry = $this->postJson('/api/v1/admin/content', $base)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.seo.robots_index', true)
            ->json('data');

        $this->patchJson(
            '/api/v1/admin/content/'.$entry['id'].'/status',
            ['status' => 'published'],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'content.review_required');

        $this->patchJson(
            '/api/v1/admin/content/'.$entry['id'].'/status',
            ['status' => 'review'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.seo.robots_index', true);

        $published = $this->patchJson(
            '/api/v1/admin/content/'.$entry['id'].'/status',
            ['status' => 'published'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.seo.robots_index', true)
            ->json('data');

        $this->patchJson('/api/v1/admin/content/'.$entry['id'], [
            'title' => 'بدون Hash',
        ])->assertUnprocessable();

        $this->patchJson('/api/v1/admin/content/'.$entry['id'], [
            'expected_content_hash' => str_repeat('0', 64),
            'title' => 'نسخه قدیمی',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'content.edit_conflict');

        $updated = $this->patchJson('/api/v1/admin/content/'.$entry['id'], [
            'expected_content_hash' => $published['content_hash'],
            'title' => 'راهنمای ویرایش‌شده',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.seo.robots_index', true)
            ->json('data');

        $this->assertNotSame($published['content_hash'], $updated['content_hash']);
        $this->getJson('/api/v1/content/test-guide')->assertNotFound();
    }

    public function test_redirects_are_internal_resolvable_and_loop_safe(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);

        $redirect = $this->postJson('/api/v1/admin/seo-redirects', [
            'source_path' => '/old-guide',
            'destination_path' => '/guides/new-guide',
            'status_code' => 301,
        ])->assertOk()->json('data');

        $this->getJson(
            '/api/v1/seo/redirects/resolve?path='.urlencode('/old-guide'),
        )
            ->assertOk()
            ->assertJsonPath('data.redirect.id', $redirect['id'])
            ->assertJsonPath(
                'data.redirect.destination_path',
                '/guides/new-guide',
            )
            ->assertJsonPath('data.redirect.hits', 1);

        $this->assertDatabaseHas('seo_redirects', [
            'id' => $redirect['id'],
            'hits' => 1,
        ]);

        $this->postJson('/api/v1/admin/seo-redirects', [
            'source_path' => '/guides/new-guide',
            'destination_path' => '/old-guide',
            'status_code' => 301,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'seo.redirect_loop');

        $this->postJson('/api/v1/admin/seo-redirects', [
            'source_path' => '/external',
            'destination_path' => 'https://example.com',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/admin/seo-redirects', [
            'source_path' => '/checkout',
            'destination_path' => '/guides/new-guide',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'seo.path_reserved');

        $this->postJson('/api/v1/admin/content', [
            'author_id' => $this->author()->id,
            'type' => 'guide',
            'title' => 'Traversal test',
            'slug' => 'traversal-test',
            'canonical_path' => '/guides/%252e%252e/private',
            'excerpt' => 'توضیح تست مسیر رمزگذاری‌شده.',
            'body' => [
                ['type' => 'paragraph', 'text' => 'پاراگراف یک.'],
                ['type' => 'paragraph', 'text' => 'پاراگراف دو.'],
            ],
            'seo_title' => 'Traversal test',
            'seo_description' => 'توضیح تست مسیر رمزگذاری‌شده.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'seo.path_invalid');
    }

    private function author(): ContentAuthor
    {
        return ContentAuthor::query()->create([
            'name' => 'تحریریه تست '.str()->random(6),
            'slug' => 'test-editorial-'.str()->lower(str()->random(8)),
            'bio' => null,
            'credentials' => [],
            'is_active' => true,
        ]);
    }

    private function entry(
        ContentAuthor $author,
        string $slug,
        string $path,
        string $status,
        bool $indexable,
    ): ContentEntry {
        return ContentEntry::query()->create([
            'author_id' => $author->id,
            'type' => 'guide',
            'title' => $slug,
            'slug' => $slug,
            'canonical_path' => $path,
            'excerpt' => 'توضیح تست برای محتوا.',
            'body' => [
                ['type' => 'paragraph', 'text' => 'متن تست محتوا.'],
                ['type' => 'heading', 'level' => 2, 'text' => 'تیتر تست'],
            ],
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'seo_title' => 'عنوان تست',
            'seo_description' => 'توضیح سئو تست.',
            'robots_index' => $indexable,
            'robots_follow' => true,
            'schema_type' => 'BlogPosting',
            'keywords' => [],
            'content_hash' => hash('sha256', $slug),
        ]);
    }
}
