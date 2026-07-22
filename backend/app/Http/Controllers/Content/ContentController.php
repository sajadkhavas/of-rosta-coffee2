<?php

namespace App\Http\Controllers\Content;

use App\Http\Requests\Content\ContentIndexRequest;
use App\Http\Resources\ContentEntryDetailResource;
use App\Http\Resources\ContentEntrySummaryResource;
use App\Models\ContentEntry;
use App\Support\SeoPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ContentController
{
    public function index(ContentIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = ContentEntry::query()
            ->published()
            ->with('author')
            ->orderByDesc('published_at');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $text = trim((string) ($filters['q'] ?? ''));
        if ($text !== '') {
            $query->where(static function (Builder $where) use ($text): void {
                $where->where('title', 'like', '%'.$text.'%')
                    ->orWhere('excerpt', 'like', '%'.$text.'%')
                    ->orWhereJsonContains('keywords', $text);
            });
        }

        return ContentEntrySummaryResource::collection(
            $query->paginate(
                perPage: (int) ($filters['per_page'] ?? 24),
                page: (int) ($filters['page'] ?? 1),
            )->withQueryString(),
        );
    }

    public function show(string $slug): ContentEntryDetailResource
    {
        $entry = ContentEntry::query()
            ->published()
            ->with(['author', 'reviewer', 'relations'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new ContentEntryDetailResource($entry);
    }

    public function resolve(Request $request): ContentEntryDetailResource
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);
        $path = SeoPath::assertPublic((string) $validated['path']);

        $entry = ContentEntry::query()
            ->published()
            ->with(['author', 'reviewer', 'relations'])
            ->where('canonical_path', $path)
            ->firstOrFail();

        return new ContentEntryDetailResource($entry);
    }
}
