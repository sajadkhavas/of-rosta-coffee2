<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Workspace\WorkspaceKpiService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AdminOperationsController extends Controller
{
    public function workspace(
        Request $request,
        CatalogAccess $access,
        WorkspaceKpiService $workspaceKpis,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return ApiResponse::success([
            'kpis' => $workspaceKpis->admin(),
            'generated_at' => now()->toImmutable()->toIso8601String(),
        ]);
    }

    public function audits(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $query = AuditLog::query()->with('actor:id,name,email,mobile')->latest('created_at');
        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $query->where('action', 'like', Str::limit($action, 120, '').'%');
        }

        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                ] : null,
                'auditable_type' => class_basename((string) $log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'request_id' => $log->request_id,
                'metadata' => $this->redactMetadata($log->metadata ?? []),
                'created_at' => $log->created_at->toIso8601String(),
            ])->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function notifications(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $query = NotificationOutbox::query()->latest('created_at');
        $status = trim((string) $request->query('status', 'failed'));
        if (in_array($status, array_column(NotificationStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (NotificationOutbox $item): array => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'order_id' => $item->order_id,
                'sub_order_id' => $item->sub_order_id,
                'channel' => $item->channel,
                'destination_hint' => $this->maskDestination((string) $item->destination),
                'template_key' => $item->template_key,
                'status' => $item->status->value,
                'provider' => $item->provider,
                'provider_message_id' => $item->provider_message_id,
                'attempts' => $item->attempts,
                'last_error' => $item->last_error === null
                    ? null
                    : Str::limit((string) $item->last_error, 500),
                'available_at' => $item->available_at->toIso8601String(),
                'processing_at' => $item->processing_at?->toIso8601String(),
                'sent_at' => $item->sent_at?->toIso8601String(),
                'failed_at' => $item->failed_at?->toIso8601String(),
                'created_at' => $item->created_at?->toIso8601String(),
            ])->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function redactMetadata(array $metadata): array
    {
        $sensitive = ['token', 'secret', 'password', 'authorization', 'cookie', 'mobile', 'email', 'destination', 'payload'];
        $safe = [];

        foreach (array_slice($metadata, 0, 30, true) as $key => $value) {
            $normalized = strtolower((string) $key);
            if (collect($sensitive)->contains(fn (string $needle): bool => str_contains($normalized, $needle))) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redactMetadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? Str::limit($value, 500) : $value;
            } else {
                $safe[$key] = '[unsupported]';
            }
        }

        return $safe;
    }

    private function maskDestination(string $destination): string
    {
        $destination = trim($destination);
        if ($destination === '') {
            return '';
        }
        if (str_contains($destination, '@')) {
            [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        $length = mb_strlen($destination);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($destination, 0, 3).str_repeat('*', max(3, $length - 6)).mb_substr($destination, -3);
    }

    /** @return array<string, int> */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
