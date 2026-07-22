<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\ProductStatus;
use App\Enums\RoasteryStatus;
use App\Models\ContentEntry;
use App\Models\ContentRelation;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Roastery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ContentLinkReportService
{
    private const MAX_RELATIONS = 5_000;
    private const MAX_ISSUES = 250;

    /** @return array<string, mixed> */
    public function build(): array
    {
        $relations = ContentRelation::query()
            ->with('entry:id,title,slug,canonical_path,status')
            ->whereHas('entry', static fn ($query) => $query
                ->where('status', '!=', ContentStatus::Archived->value))
            ->orderBy('id')
            ->limit(self::MAX_RELATIONS + 1)
            ->get();

        $truncated = $relations->count() > self::MAX_RELATIONS;
        if ($truncated) {
            /** @var Collection<int, ContentRelation> $relations */
            $relations = new Collection($relations->take(self::MAX_RELATIONS)->all());
        }

        $targetMaps = $this->targetMaps($relations);
        $broken = [];
        foreach ($relations as $relation) {
            $reason = $this->brokenReason($relation, $targetMaps);
            if ($reason === null) {
                continue;
            }

            $broken[] = [
                'relation_id' => $relation->id,
                'source' => [
                    'id' => $relation->entry?->id,
                    'title' => $relation->entry?->title,
                    'slug' => $relation->entry?->slug,
                    'canonical_path' => $relation->entry?->canonical_path,
                    'status' => $relation->entry?->status?->value,
                ],
                'relation_type' => $relation->relation_type,
                'target_type' => $relation->target_type,
                'target_key' => $relation->target_key,
                'anchor_text' => $relation->anchor_text,
                'reason' => $reason,
            ];

            if (count($broken) >= self::MAX_ISSUES) {
                break;
            }
        }

        $incomingContentSlugs = ContentRelation::query()
            ->where('target_type', 'content')
            ->select('target_key');

        $orphaned = ContentEntry::query()
            ->published()
            ->whereNotIn('slug', $incomingContentSlugs)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ISSUES)
            ->get(['id', 'title', 'slug', 'canonical_path', 'updated_at'])
            ->map(static fn (ContentEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'canonical_path' => $entry->canonical_path,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $weakOutbound = ContentEntry::query()
            ->published()
            ->has('relations', '<', 2)
            ->withCount('relations')
            ->orderBy('relations_count')
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ISSUES)
            ->get()
            ->map(static fn (ContentEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'canonical_path' => $entry->canonical_path,
                'relations_count' => (int) $entry->relations_count,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $statusCounts = ContentEntry::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'entries_by_status' => [
                    'draft' => $statusCounts[ContentStatus::Draft->value] ?? 0,
                    'review' => $statusCounts[ContentStatus::Review->value] ?? 0,
                    'published' => $statusCounts[ContentStatus::Published->value] ?? 0,
                    'archived' => $statusCounts[ContentStatus::Archived->value] ?? 0,
                ],
                'total_relations_scanned' => $relations->count(),
                'relations_truncated' => $truncated,
                'broken_relations' => count($broken),
                'orphaned_entries' => count($orphaned),
                'weak_outbound_entries' => count($weakOutbound),
            ],
            'broken_relations' => $broken,
            'orphaned_entries' => $orphaned,
            'weak_outbound_entries' => $weakOutbound,
        ];
    }

    /**
     * @param Collection<int, ContentRelation> $relations
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function targetMaps(Collection $relations): array
    {
        $keys = $relations
            ->groupBy('target_type')
            ->map(static fn (Collection $items): array => $items
                ->pluck('target_key')
                ->filter()
                ->unique()
                ->values()
                ->all());

        $contentKeys = array_values(array_unique(array_merge(
            $keys->get('content', []),
            $keys->get('brew_method', []),
            $keys->get('taste', []),
        )));

        $content = ContentEntry::query()
            ->whereIn('slug', $contentKeys)
            ->get(['slug', 'type', 'status', 'published_at'])
            ->keyBy('slug')
            ->map(static fn (ContentEntry $entry): array => [
                'type' => $entry->type->value,
                'live' => $entry->status === ContentStatus::Published
                    && $entry->published_at !== null
                    && $entry->published_at->isPast(),
            ])
            ->all();

        $products = Product::query()
            ->whereIn('slug', $keys->get('product', []))
            ->get(['slug', 'status', 'published_at'])
            ->keyBy('slug')
            ->map(static fn (Product $product): array => [
                'live' => $product->status === ProductStatus::Published
                    && $product->published_at !== null,
            ])
            ->all();

        $roasteries = Roastery::query()
            ->whereIn('slug', $keys->get('roastery', []))
            ->get(['slug', 'status', 'verified_at'])
            ->keyBy('slug')
            ->map(static fn (Roastery $roastery): array => [
                'live' => $roastery->status === RoasteryStatus::Verified
                    && $roastery->verified_at !== null,
            ])
            ->all();

        $origins = Origin::query()
            ->whereIn('slug', $keys->get('origin', []))
            ->pluck('slug')
            ->mapWithKeys(static fn (string $slug): array => [$slug => ['live' => true]])
            ->all();

        return [
            'content' => $content,
            'product' => $products,
            'roastery' => $roasteries,
            'origin' => $origins,
            'brew_method' => $content,
            'taste' => $content,
        ];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $maps
     */
    private function brokenReason(ContentRelation $relation, array $maps): ?string
    {
        $target = $maps[$relation->target_type][$relation->target_key] ?? null;
        if ($target === null) {
            return 'missing_target';
        }

        if ($relation->target_type === 'brew_method'
            && ($target['type'] ?? null) !== ContentType::BrewMethod->value) {
            return 'wrong_content_type';
        }

        if ($relation->target_type === 'taste'
            && ($target['type'] ?? null) !== ContentType::Taste->value) {
            return 'wrong_content_type';
        }

        if (($target['live'] ?? false) !== true) {
            return 'unpublished_target';
        }

        return null;
    }
}
