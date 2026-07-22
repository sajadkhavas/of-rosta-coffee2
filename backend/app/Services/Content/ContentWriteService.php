<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
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
            unset($data['relations']);

            $body = $this->blocks->validate($data['body']);
            $entry = ContentEntry::query()->create([
                ...$this->normalizedData($data, $body),
                'status' => ContentStatus::Draft->value,
                'robots_index' => false,
            ]);

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
            $locked = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $relationsProvided = array_key_exists('relations', $data);
            $relations = $data['relations'] ?? [];
            unset($data['relations']);

            $body = array_key_exists('body', $data)
                ? $this->blocks->validate($data['body'])
                : $locked->body;

            $wasPublished = $locked->status === ContentStatus::Published;
            $locked->fill($this->normalizedData($data, $body));
            if ($wasPublished) {
                $locked->status = ContentStatus::Review;
                $locked->published_at = null;
                $locked->robots_index = false;
            }
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
    private function normalizedData(array $data, array $body): array
    {
        if (array_key_exists('canonical_path', $data)) {
            $data['canonical_path'] = SeoPath::assertPublic((string) $data['canonical_path']);
        }

        $data['body'] = $body;
        $data['content_hash'] = $this->blocks->hash($body);
        $data['keywords'] = array_values(array_unique(array_map(
            static fn (mixed $keyword): string => trim((string) $keyword),
            $data['keywords'] ?? [],
        )));
        $data['schema_type'] ??= 'Article';
        $data['robots_follow'] ??= true;

        return $data;
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
