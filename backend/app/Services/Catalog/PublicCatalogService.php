<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\Roastery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PublicCatalogService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Product>
     */
    public function products(array $filters): LengthAwarePaginator
    {
        $query = $this->publicProductQuery()
            ->withMin([
                'variants as min_active_price' => static fn ($variants) => $variants->where('is_active', true),
            ], 'price');

        $text = trim((string) ($filters['q'] ?? ''));
        if ($text !== '') {
            $query->where(static function (Builder $where) use ($text): void {
                $where->where('name', 'like', '%'.$text.'%')
                    ->orWhere('short_description', 'like', '%'.$text.'%')
                    ->orWhereJsonContains('tasting_notes', $text);
            });
        }

        if (($filters['origin'] ?? []) !== []) {
            $origins = $filters['origin'];
            $query->whereHas('origin', static function (Builder $origin) use ($origins): void {
                $origin->whereIn('id', $origins)
                    ->orWhereIn('slug', $origins)
                    ->orWhereIn('name', $origins);
            });
        }

        if (($filters['roast_level'] ?? []) !== []) {
            $query->whereIn('roast_level', $filters['roast_level']);
        }

        if (($filters['processing_method'] ?? []) !== []) {
            $query->whereIn('processing_method', $filters['processing_method']);
        }

        if (($filters['roastery'] ?? []) !== []) {
            $roasteries = $filters['roastery'];
            $query->whereHas('roastery', static function (Builder $roastery) use ($roasteries): void {
                $roastery->whereIn('id', $roasteries)
                    ->orWhereIn('slug', $roasteries);
            });
        }

        if (($filters['weight'] ?? []) !== []) {
            $weights = array_map('intval', $filters['weight']);
            $query->whereHas('variants', static fn (Builder $variant): Builder => $variant->where('is_active', true)->whereIn('weight_grams', $weights));
        }

        if (array_key_exists('min_price', $filters)) {
            $minimum = (int) $filters['min_price'];
            $query->whereHas('variants', static fn (Builder $variant): Builder => $variant->where('is_active', true)->where('price', '>=', $minimum));
        }

        if (array_key_exists('max_price', $filters)) {
            $maximum = (int) $filters['max_price'];
            $query->whereHas('variants', static fn (Builder $variant): Builder => $variant->where('is_active', true)->where('price', '<=', $maximum));
        }

        if (($filters['available'] ?? null) !== null) {
            $available = (bool) $filters['available'];
            $method = $available ? 'whereHas' : 'whereDoesntHave';
            $query->{$method}('variants', static fn (Builder $variant): Builder => $variant->where('is_active', true)
                ->whereColumn('stock_on_hand', '>', 'stock_reserved'));
        }

        match ($filters['sort'] ?? 'recommended') {
            'newest' => $query->orderByDesc('published_at'),
            'price_asc' => $query->orderBy('min_active_price')->orderByDesc('published_at'),
            'price_desc' => $query->orderByDesc('min_active_price')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at')->orderBy('name'),
        };

        return $query->paginate(
            perPage: (int) ($filters['per_page'] ?? 24),
            page: (int) ($filters['page'] ?? 1),
        )->withQueryString();
    }

    public function product(string $slug): Product
    {
        return $this->publicProductQuery()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 8): Collection
    {
        return $this->publicProductQuery()
            ->whereKeyNot($product->id)
            ->where(static function (Builder $related) use ($product): void {
                $related->where('origin_id', $product->origin_id)
                    ->orWhere('roast_level', $product->roast_level->value)
                    ->orWhere('roastery_id', $product->roastery_id);
            })
            ->limit(max(1, min(24, $limit)))
            ->get();
    }

    /**
     * @return array{products: Collection<int, Product>, roasteries: Collection<int, Roastery>, suggestions: list<string>}
     */
    public function search(string $text, string $type): array
    {
        $products = collect();
        $roasteries = collect();

        if (in_array($type, ['all', 'products'], true)) {
            $products = $this->publicProductQuery()
                ->where(static function (Builder $query) use ($text): void {
                    $query->where('name', 'like', '%'.$text.'%')
                        ->orWhere('short_description', 'like', '%'.$text.'%')
                        ->orWhereJsonContains('tasting_notes', $text);
                })
                ->limit(30)
                ->get();
        }

        if (in_array($type, ['all', 'roasteries'], true)) {
            $roasteries = Roastery::query()
                ->with(['logo', 'cover'])
                ->where('status', 'verified')
                ->whereNotNull('verified_at')
                ->where(static function (Builder $query) use ($text): void {
                    $query->where('name', 'like', '%'.$text.'%')
                        ->orWhere('city', 'like', '%'.$text.'%')
                        ->orWhere('description', 'like', '%'.$text.'%');
                })
                ->limit(30)
                ->get();
        }

        $suggestions = $products
            ->flatMap(static fn (Product $product): array => $product->tasting_notes ?? [])
            ->filter(static fn (mixed $note): bool => is_string($note) && $note !== '')
            ->unique()
            ->take(10)
            ->values()
            ->all();

        return compact('products', 'roasteries', 'suggestions');
    }

    /** @return Builder<Product> */
    private function publicProductQuery(): Builder
    {
        return Product::query()
            ->published()
            ->with([
                'origin',
                'primaryImage',
                'gallery',
                'latestRoastBatch',
                'roastery.logo',
                'roastery.cover',
                'variants' => static fn ($variants) => $variants->where('is_active', true)->orderBy('weight_grams'),
                'variants.wholesaleTiers' => static fn ($tiers) => $tiers->where('is_active', true)->orderBy('min_weight_grams'),
            ]);
    }
}
