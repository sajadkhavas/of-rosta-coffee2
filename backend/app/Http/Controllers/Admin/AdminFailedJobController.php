<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Operations\FailedJobReasonRequest;
use App\Models\FailedJobOperation;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Operations\FailedJobOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminFailedJobController
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $page = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'failed_at'])
            ->orderByDesc('failed_at')
            ->paginate(
                perPage: max(1, min(100, (int) $request->query('per_page', 50))),
                page: max(1, (int) $request->query('page', 1)),
            );

        return ApiResponse::success([
            'items' => collect($page->items())->map(function (object $job): array {
                $payload = json_decode((string) $job->payload, true);
                $displayName = is_array($payload) && is_string($payload['displayName'] ?? null)
                    ? Str::limit($payload['displayName'], 180, '')
                    : 'queued_job';

                return [
                    'uuid' => (string) $job->uuid,
                    'connection' => Str::limit((string) $job->connection, 80, ''),
                    'queue' => Str::limit((string) $job->queue, 80, ''),
                    'job' => $displayName,
                    'failed_at' => (string) $job->failed_at,
                ];
            })->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function retry(
        FailedJobReasonRequest $request,
        string $uuid,
        CatalogAccess $access,
        FailedJobOperationsService $operations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $operations->retry($user, $uuid, $request->string('reason')->toString(), $request);

        return ApiResponse::success(['uuid' => $uuid, 'status' => 'retry_requested']);
    }

    public function requestForget(
        FailedJobReasonRequest $request,
        string $uuid,
        CatalogAccess $access,
        FailedJobOperationsService $operations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $operation = $operations->requestForget(
            $user,
            $uuid,
            $request->string('reason')->toString(),
            $request,
        );

        return ApiResponse::success($this->operationPayload($operation), 201);
    }

    public function confirmForget(
        Request $request,
        string $operationId,
        CatalogAccess $access,
        FailedJobOperationsService $operations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $operation = FailedJobOperation::query()->findOrFail($operationId);
        $confirmed = $operations->confirmForget($user, $operation, $request);

        return ApiResponse::success($this->operationPayload($confirmed));
    }

    /** @return array<string, mixed> */
    private function operationPayload(FailedJobOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'failed_job_uuid' => $operation->failed_job_uuid,
            'action' => $operation->action,
            'status' => $operation->status,
            'reason' => $operation->reason,
            'requested_by_id' => $operation->requested_by_id,
            'confirmed_by_id' => $operation->confirmed_by_id,
            'requested_at' => $operation->requested_at?->toIso8601String(),
            'confirmed_at' => $operation->confirmed_at?->toIso8601String(),
        ];
    }
}
