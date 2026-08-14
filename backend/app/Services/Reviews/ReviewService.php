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
    public function __construct(private readonly AuditRecorder $audit) {}

    public function create(User $user, array $input, Request $request): Review
    {
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
                throw new ApiDomainException('review.order_not_delivered', 'ثبت نظر فقط پس از تحویل سفارش امکان‌پذیر است.', 409);
            }
            if (Review::query()->where('order_item_id', $item->id)->exists()) {
                throw new ApiDomainException('review.already_submitted', 'برای این آیتم سفارش قبلاً نظر ثبت شده است.', 409);
            }
            $review = Review::query()->create([
                'user_id' => $user->id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'roastery_id' => $item->order->roastery_id,
                'rating' => $input['rating'],
                'title' => $this->cleanNullableText($input['title'] ?? null, 160),
                'body' => $this->cleanText((string) $input['body'], 5000),
                'status' => ReviewStatus::Pending,
                'is_verified_purchase' => true,
            ]);
            $this->audit->record('review.created', actor: $user, auditable: $review, metadata: [
                'order_id' => $review->order_id,
                'order_item_id' => $review->order_item_id,
                'product_id' => $review->product_id,
                'rating' => $review->rating,
            ], request: $request);

            return $review->load(['user', 'product']);
        }, 3);
    }

    public function moderate(User $administrator, Review $review, ReviewStatus $status, ?string $reason, Request $request): Review
    {
        if (! in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            throw new ApiDomainException('review.moderation_invalid', 'تصمیم Moderation معتبر نیست.', 422);
        }

        return DB::transaction(function () use ($administrator, $review, $status, $reason, $request): Review {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => $status,
                'moderated_by' => $administrator->id,
                'moderated_at' => now(),
                'moderation_reason' => $this->cleanNullableText($reason, 500),
            ])->save();
            $this->audit->record('review.moderated', actor: $administrator, auditable: $locked, metadata: [
                'status' => $status->value,
                'product_id' => $locked->product_id,
            ], request: $request);

            return $locked->load(['user', 'product', 'moderator']);
        }, 3);
    }

    public function publicForProduct(Product $product, int $limit = 20): array
    {
        $query = Review::query()
            ->where('product_id', $product->id)
            ->where('status', ReviewStatus::Approved->value)
            ->where('is_verified_purchase', true);
        $count = (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('rating'), 2) : null;
        $items = $query->with(['user:id,name', 'reply'])
            ->latest('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (Review $review): array => $this->publicPayload($review))
            ->all();

        return ['summary' => ['count' => $count, 'average' => $average], 'items' => $items];
    }

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

    private function publicPayload(Review $review): array
    {
        $reply = $review->reply;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => $this->publicName($review->user?->name),
            'is_verified_purchase' => true,
            'created_at' => $review->created_at?->toIso8601String(),
            'seller_reply' => $reply && $reply->status === 'visible' ? [
                'body' => $reply->body,
                'updated_at' => $reply->updated_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function publicName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? 'خریدار رستا' : mb_substr($name, 0, 1).'***';
    }

    private function cleanNullableText(?string $value, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->cleanText($value, $max);
    }

    private function cleanText(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($value === '') {
            throw new ApiDomainException('review.content_empty', 'متن نمی‌تواند خالی باشد.', 422);
        }

        return mb_substr($value, 0, $max);
    }
}
