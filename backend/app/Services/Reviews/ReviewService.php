<?php

namespace App\Services\Reviews;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\ApiDomainException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReviewService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{order_item_id: string, rating: int, title?: string|null, body: string}  $input
     */
    public function create(
        User $user,
        array $input,
        Request $request,
    ): Review {
        return DB::transaction(function () use ($user, $input, $request): Review {
            $item = OrderItem::query()
                ->with(['order', 'product'])
                ->whereKey($input['order_item_id'])
                ->whereHas('order', static function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                })
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->order->status !== OrderStatus::Delivered) {
                throw new ApiDomainException(
                    'review.order_not_delivered',
                    'ثبت نظر فقط پس از تحویل سفارش امکان‌پذیر است.',
                    409,
                );
            }

            if (Review::query()->where('order_item_id', $item->id)->exists()) {
                throw new ApiDomainException(
                    'review.already_submitted',
                    'برای این آیتم سفارش قبلاً نظر ثبت شده است.',
                    409,
                );
            }

            $review = Review::query()->create([
                'user_id' => $user->id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'roastery_id' => $item->order->roastery_id,
                'rating' => $input['rating'],
                'title' => $this->nullableTrim($input['title'] ?? null),
                'body' => trim($input['body']),
                'status' => ReviewStatus::Pending,
                'is_verified_purchase' => true,
            ]);

            $this->audit->record(
                'review.created',
                actor: $user,
                auditable: $review,
                metadata: [
                    'order_id' => $review->order_id,
                    'order_item_id' => $review->order_item_id,
                    'product_id' => $review->product_id,
                    'rating' => $review->rating,
                ],
                request: $request,
            );

            return $review->load(['user', 'product']);
        }, 3);
    }

    public function moderate(
        User $administrator,
        Review $review,
        ReviewStatus $status,
        ?string $reason,
        Request $request,
    ): Review {
        if (! in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            throw new ApiDomainException(
                'review.moderation_invalid',
                'تصمیم Moderation معتبر نیست.',
                422,
            );
        }

        return DB::transaction(function () use (
            $administrator,
            $review,
            $status,
            $reason,
            $request,
        ): Review {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => $status,
                'moderated_by' => $administrator->id,
                'moderated_at' => now(),
                'moderation_reason' => $this->nullableTrim($reason),
            ])->save();

            $this->audit->record(
                'review.moderated',
                actor: $administrator,
                auditable: $locked,
                metadata: [
                    'status' => $status->value,
                    'product_id' => $locked->product_id,
                ],
                request: $request,
            );

            return $locked->load(['user', 'product', 'moderator']);
        }, 3);
    }

    /**
     * @return array{summary: array{count: int, average: float|null}, items: array<int, array<string, mixed>>}
     */
    public function publicForProduct(Product $product, int $limit = 20): array
    {
        $query = Review::query()
            ->where('product_id', $product->id)
            ->where('status', ReviewStatus::Approved->value)
            ->where('is_verified_purchase', true);
        $count = (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('rating'), 2) : null;
        $items = $query
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (Review $review): array => $this->publicPayload($review))
            ->all();

        return [
            'summary' => ['count' => $count, 'average' => $average],
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    public function privatePayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'order_id' => $review->order_id,
            'order_item_id' => $review->order_item_id,
            'product_id' => $review->product_id,
            'roastery_id' => $review->roastery_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status->value,
            'is_verified_purchase' => $review->is_verified_purchase,
            'moderated_at' => $review->moderated_at?->toIso8601String(),
            'moderation_reason' => $review->moderation_reason,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => $this->publicName($review->user?->name),
            'is_verified_purchase' => true,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    private function publicName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'خریدار رستا';
        }

        return mb_substr($name, 0, 1).'***';
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
