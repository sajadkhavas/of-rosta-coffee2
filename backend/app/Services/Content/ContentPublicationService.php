<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Exceptions\ApiDomainException;
use App\Models\ContentEntry;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContentPublicationService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function setStatus(
        ContentEntry $entry,
        ContentStatus $status,
        User $reviewer,
        Request $request,
    ): ContentEntry {
        return DB::transaction(function () use (
            $entry,
            $status,
            $reviewer,
            $request,
        ): ContentEntry {
            $locked = ContentEntry::query()
                ->with('author')
                ->lockForUpdate()
                ->findOrFail($entry->id);
            $previousStatus = $locked->status;

            if ($status === ContentStatus::Published) {
                if (
                    $previousStatus !== ContentStatus::Review
                    && $previousStatus !== ContentStatus::Published
                ) {
                    throw new ApiDomainException(
                        'content.review_required',
                        'محتوا پیش از انتشار باید وارد وضعیت بررسی شود.',
                        409,
                    );
                }

                if ($locked->author_id === null || ! $locked->author?->is_active) {
                    throw new ApiDomainException(
                        'content.author_required',
                        'انتشار محتوا به نویسنده فعال نیاز دارد.',
                        409,
                    );
                }

                if (count($locked->body) < 2) {
                    throw new ApiDomainException(
                        'content.body_too_short',
                        'برای انتشار حداقل دو بلوک محتوایی لازم است.',
                        409,
                    );
                }

                if (trim((string) ($locked->seo_title ?: $locked->title)) === '') {
                    throw new ApiDomainException(
                        'content.seo_title_required',
                        'عنوان سئو برای انتشار لازم است.',
                        409,
                    );
                }

                if (trim((string) ($locked->seo_description ?: $locked->excerpt)) === '') {
                    throw new ApiDomainException(
                        'content.seo_description_required',
                        'توضیح سئو برای انتشار لازم است.',
                        409,
                    );
                }
            }

            $locked->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'published_at' => $status === ContentStatus::Published
                    ? ($locked->published_at ?? now())
                    : null,
            ])->save();

            $this->audit->record(
                'content.entry.status_changed',
                actor: $reviewer,
                auditable: $locked,
                metadata: [
                    'previous_status' => $previousStatus->value,
                    'status' => $status->value,
                    'robots_index' => $locked->robots_index,
                ],
                request: $request,
            );

            return $locked->refresh()->load([
                'author',
                'reviewer',
                'relations',
            ]);
        });
    }
}
