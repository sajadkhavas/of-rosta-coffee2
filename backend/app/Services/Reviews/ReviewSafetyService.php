<?php

namespace App\Services\Reviews;

use App\Enums\ReviewStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewReplyRevision;
use App\Models\ReviewReport;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReviewSafetyService
{
    public function __construct(private readonly CatalogAccess $access, private readonly AuditRecorder $audit) {}

    public function sellerReviews(User $user, Roastery $roastery, int $limit = 50): array
    {
        $this->access->assertRoasteryAccess($user, $roastery);

        return Review::query()
            ->where('roastery_id', $roastery->id)
            ->with(['reply', 'product'])
            ->latest()
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (Review $review): array => $this->sellerPayload($review))
            ->all();
    }

    public function upsertReply(User $user, Roastery $roastery, Review $review, string $body, Request $request): ReviewReply
    {
        $this->access->assertRoasteryAccess($user, $roastery);
        if ($review->roastery_id !== $roastery->id) {
            abort(404);
        }
        $body = $this->cleanText($body, 5000);

        return DB::transaction(function () use ($user, $roastery, $review, $body, $request): ReviewReply {
            $reply = ReviewReply::query()->where('review_id', $review->id)->lockForUpdate()->first();
            if ($reply) {
                ReviewReplyRevision::query()->create([
                    'reply_id' => $reply->id,
                    'editor_id' => $user->id,
                    'body' => $reply->body,
                    'previous_status' => $reply->status,
                    'created_at' => now(),
                ]);
                $wasPublic = $reply->status === 'visible';
                $reply->forceFill([
                    'author_id' => $user->id,
                    'body' => $body,
                    'status' => $wasPublic ? 'visible' : 'hidden',
                    'moderated_by' => $wasPublic ? null : $reply->moderated_by,
                    'moderated_at' => $wasPublic ? null : $reply->moderated_at,
                    'moderation_reason' => $wasPublic ? null : $reply->moderation_reason,
                ])->save();
                $action = 'review.reply.updated';
            } else {
                $reply = ReviewReply::query()->create([
                    'review_id' => $review->id,
                    'roastery_id' => $roastery->id,
                    'author_id' => $user->id,
                    'body' => $body,
                    'status' => 'visible',
                ]);
                $action = 'review.reply.created';
            }
            $this->audit->record($action, actor: $user, auditable: $reply, metadata: [
                'review_id' => $review->id,
                'roastery_id' => $roastery->id,
                'status' => $reply->status,
            ], request: $request);

            return $reply;
        }, 3);
    }

    public function report(User $user, Review $review, string $reason, ?string $evidence, Request $request): ReviewReport
    {
        if ($review->status !== ReviewStatus::Approved || ! $review->is_verified_purchase) {
            abort(404);
        }
        if (! in_array($reason, ReviewReport::REASONS, true)) {
            throw new ApiDomainException('review.report_reason_invalid', 'دلیل گزارش معتبر نیست.', 422);
        }
        $evidence = $evidence === null ? null : $this->cleanText($evidence, 500);

        return DB::transaction(function () use ($user, $review, $reason, $evidence, $request): ReviewReport {
            $existing = ReviewReport::query()->where('review_id', $review->id)->where('user_id', $user->id)->first();
            if ($existing) {
                return $existing;
            }
            $report = ReviewReport::query()->create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'evidence' => $evidence,
                'status' => 'open',
            ]);
            $this->audit->record('review.report.created', actor: $user, auditable: $report, metadata: [
                'review_id' => $review->id,
                'reason' => $reason,
            ], request: $request);

            return $report;
        }, 3);
    }

    public function moderateReport(User $admin, ReviewReport $report, string $status, ?string $reason, Request $request): ReviewReport
    {
        $this->access->assertAdministrator($admin);
        if (! in_array($status, ReviewReport::STATUSES, true)) {
            throw new ApiDomainException('review.report_status_invalid', 'وضعیت گزارش معتبر نیست.', 422);
        }
        $report->forceFill([
            'status' => $status,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
            'resolution_reason' => $reason === null ? null : $this->cleanText($reason, 500),
        ])->save();
        $this->audit->record('review.report.moderated', actor: $admin, auditable: $report, metadata: [
            'review_id' => $report->review_id,
            'status' => $status,
        ], request: $request);

        return $report;
    }

    public function moderateReply(User $admin, ReviewReply $reply, string $status, ?string $reason, Request $request): ReviewReply
    {
        $this->access->assertAdministrator($admin);
        if (! in_array($status, ['visible', 'hidden', 'rejected'], true)) {
            throw new ApiDomainException('review.reply_status_invalid', 'وضعیت پاسخ معتبر نیست.', 422);
        }
        $reply->forceFill([
            'status' => $status,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
            'moderation_reason' => $reason === null ? null : $this->cleanText($reason, 500),
        ])->save();
        $this->audit->record('review.reply.moderated', actor: $admin, auditable: $reply, metadata: [
            'review_id' => $reply->review_id,
            'status' => $status,
        ], request: $request);

        return $reply;
    }

    public function sellerPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'product_id' => $review->product_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status->value,
            'is_verified_purchase' => $review->is_verified_purchase,
            'created_at' => $review->created_at?->toIso8601String(),
            'reply' => $review->reply ? $this->replyPayload($review->reply) : null,
        ];
    }

    public function replyPayload(ReviewReply $reply): array
    {
        return [
            'id' => $reply->id,
            'review_id' => $reply->review_id,
            'body' => $reply->body,
            'status' => $reply->status,
            'updated_at' => $reply->updated_at?->toIso8601String(),
        ];
    }

    public function reportPayload(ReviewReport $report): array
    {
        return [
            'id' => $report->id,
            'review_id' => $report->review_id,
            'reason' => $report->reason,
            'evidence' => $report->evidence,
            'status' => $report->status,
            'created_at' => $report->created_at?->toIso8601String(),
            'moderated_at' => $report->moderated_at?->toIso8601String(),
            'resolution_reason' => $report->resolution_reason,
        ];
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
