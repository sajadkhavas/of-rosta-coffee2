<?php

namespace App\Services\Operations;

use App\Exceptions\ApiDomainException;
use App\Models\FailedJobOperation;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class FailedJobOperationsService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function retry(User $actor, string $uuid, string $reason, Request $request): void
    {
        $job = DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue'])
            ->where('uuid', $uuid)
            ->first();
        if ($job === null) {
            throw new ApiDomainException('operations.failed_job_not_found', 'کار ناموفق پیدا نشد.', 404);
        }

        $exit = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exit !== 0) {
            throw new ApiDomainException(
                'operations.failed_job_retry_failed',
                'درخواست تلاش مجدد صف پذیرفته نشد و نیاز به بررسی عملیاتی دارد.',
                503,
            );
        }

        $this->audit->record(
            'operations.failed_job_retried',
            actor: $actor,
            metadata: [
                'failed_job_uuid' => $uuid,
                'connection' => (string) $job->connection,
                'queue' => (string) $job->queue,
                'reason' => mb_substr(trim($reason), 0, 500),
            ],
            request: $request,
        );
    }

    public function requestForget(User $actor, string $uuid, string $reason, Request $request): FailedJobOperation
    {
        return DB::transaction(function () use ($actor, $uuid, $reason, $request): FailedJobOperation {
            $exists = DB::table('failed_jobs')->where('uuid', $uuid)->lockForUpdate()->exists();
            if (! $exists) {
                throw new ApiDomainException('operations.failed_job_not_found', 'کار ناموفق پیدا نشد.', 404);
            }

            $existing = FailedJobOperation::query()
                ->where('failed_job_uuid', $uuid)
                ->where('action', 'forget')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();
            if ($existing instanceof FailedJobOperation) {
                return $existing;
            }

            $operation = FailedJobOperation::query()->create([
                'failed_job_uuid' => $uuid,
                'action' => 'forget',
                'status' => 'pending',
                'reason' => trim($reason),
                'requested_by_id' => $actor->id,
                'requested_at' => now(),
            ]);

            $this->audit->record(
                'operations.failed_job_forget_requested',
                actor: $actor,
                auditable: $operation,
                metadata: [
                    'failed_job_uuid' => $uuid,
                    'reason' => mb_substr(trim($reason), 0, 500),
                ],
                request: $request,
            );

            return $operation;
        }, 3);
    }

    public function confirmForget(User $actor, FailedJobOperation $operation, Request $request): FailedJobOperation
    {
        return DB::transaction(function () use ($actor, $operation, $request): FailedJobOperation {
            $locked = FailedJobOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->action !== 'forget' || $locked->status !== 'pending') {
                throw new ApiDomainException('operations.failed_job_action_not_pending', 'این درخواست دیگر قابل تأیید نیست.', 409);
            }
            if ($locked->requested_by_id === $actor->id) {
                throw new ApiDomainException(
                    'operations.failed_job_dual_control_required',
                    'درخواست‌کننده حذف نمی‌تواند همان درخواست را تأیید کند.',
                    409,
                );
            }
            if (! DB::table('failed_jobs')->where('uuid', $locked->failed_job_uuid)->exists()) {
                throw new ApiDomainException(
                    'operations.failed_job_not_found',
                    'کار ناموفق پیش از تأیید حذف دیگر وجود ندارد.',
                    409,
                );
            }

            $exit = Artisan::call('queue:forget', ['id' => $locked->failed_job_uuid]);
            if ($exit !== 0) {
                throw new ApiDomainException(
                    'operations.failed_job_forget_failed',
                    'حذف کنترل‌شده کار ناموفق انجام نشد.',
                    503,
                );
            }

            $locked->forceFill([
                'status' => 'completed',
                'confirmed_by_id' => $actor->id,
                'confirmed_at' => now(),
            ])->save();

            $this->audit->record(
                'operations.failed_job_forgotten',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'failed_job_uuid' => $locked->failed_job_uuid,
                    'requested_by_id' => $locked->requested_by_id,
                ],
                request: $request,
            );

            return $locked;
        }, 3);
    }
}
