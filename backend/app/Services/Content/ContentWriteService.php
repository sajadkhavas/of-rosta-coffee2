<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Exceptions\ApiDomainException;
use App\Models\ContentEntry;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\SeoPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContentWriteService
{
    public function __construct(
        private readonly ContentBlockValidator $blocks,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, Request $request): ContentEntry
    {
        return DB::transaction(function () use ($data, $actor, $request): ContentEntry {
            $relations = $data['relations'] ?? [];
            unset($data['relations'], $data['expected_content_hash']);

            $body = $this->blocks->validate($data['body']);
            $entry = ContentEntry::query()->create([
                ...$this->normalizedData($data, $body, true),
                'status' => ContentStatus::Draft->value,
            ]);
            $entry->forceFill([
                'content_hash' => $this->documentHash($entry),
            ])->save();

            $this->syncRelations($entry, $relations);
            $this->audit->record(
                'content.entry.created',
                actor: $actor,
                auditable: $entry,
                metadata: ['type' => $entry->type->value],
                request: $request,
            );

            return $entry->load(['author', 'reviewer', 'relations']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        ContentEntry $entry,
        array $data,
        User $actor,
        Request $request,
    ): ContentEntry {
        return DB::transaction(function () use ($entry, $data, $actor, $request): ContentEntry {
            $locked = ContentEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            $expectedHash = (string) ($data['expected_content_hash'] ?? '');
            unset($data['expected_content_hash']);
            if (! hash_equals((string) $locked->content_hash, $expectedHash)) {
                throw new ApiDomainException(
                    'content.edit_conflict',
                    'این محتوا پس از بازشدن ویرایش شده است. نسخه جدید را دریافت و تغییرات را دوباره اعمال کنید.',
                    409,
                );
            }

            $relationsProvided = array_key_exists('relations', $data);
            $relations = $data['relations'] ?? [];
            unset($data['relations']);

            $body = array_key_exists('body', $data)
                ? $this->blocks->validate($data['body'])
                : $locked->body;

            $wasPublished = $locked->status === ContentStatus::Published;
            $previousHash = (string) $locked->content_hash;
            $locked->fill($this->normalizedData($data, $body));
            if ($wasPublished) {
                $locked->status = ContentStatus::Review;
                $locked->published_at = null;
            }
            $locked->content_hash = $this->documentHash($locked);
            $locked->save();

            if ($relationsProvided) {
                $this->syncRelations($locked, $relations);
            }

            $this->audit->record(
                'content.entry.updated',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'fields' => array_keys($data),
                    'previous_content_hash' => $previousHash,
                    'content_hash' => $locked->content_hash,
                    'returned_to_review' => $wasPublished,
                ],
                request: $request,
            );

            return $locked->refresh()->load(['author', 'reviewer', 'relations']);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $body
     * @return array<string, mixed>
     */
    private function normalizedData(
        array $data,
        array $body,
        bool $isCreate = false,
    ): array {
        if (array_key_exists('canonical_path', $data)) {
            $data['canonical_path'] = SeoPath::assertPublic(
                (string) $data['canonical_path'],
            );
        }

        $data['body'] = $body;
        $data['content_hash'] = $this->blocks->hash($body);

        if (array_key_exists('keywords', $data)) {
            $data['keywords'] = array_values(array_unique(array_filter(array_map(
                static fn (mixed $keyword): string => trim((string) $keyword),
                $data['keywords'],
            ))));
        } elseif ($isCreate) {
            $data['keywords'] = [];
        }

        if ($isCreate) {
            $data['schema_type'] ??= 'Article';
            $data['robots_index'] ??= false;
            $data['robots_follow'] ??= true;
        }

        return $data;
    }

    private function documentHash(ContentEntry $entry): string
    {
        $attributes = $entry->getAttributes();
        $document = [];
        foreach ([
            'author_id',
            'type',
            'title',
            'slug',
            'canonical_path',
            'excerpt',
            'body',
            'seo_title',
            'seo_description',
            'robots_index',
            'robots_follow',
            'og_title',
            'og_description',
            'og_media_url',
            'schema_type',
            'keywords',
        ] as $field) {
            $document[$field] = $attributes[$field] ?? null;
        }

        return hash('sha256', json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @param list<array<string, mixed>> $relations
     */
    private function syncRelations(ContentEntry $entry, array $relations): void
    {
        $entry->relations()->delete();
        foreach ($relations as $index => $relation) {
            $entry->relations()->create([
                'relation_type' => $relation['relation_type'],
                'target_type' => $relation['target_type'],
                'target_key' => trim((string) $relation['target_key']),
                'anchor_text' => isset($relation['anchor_text'])
                    ? trim((string) $relation['anchor_text'])
                    : null,
                'position' => (int) ($relation['position'] ?? $index),
            ]);
        }
    }
}
