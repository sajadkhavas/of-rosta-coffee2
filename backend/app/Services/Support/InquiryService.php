<?php

namespace App\Services\Support;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\IranMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

final class InquiryService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{type: string, name: string, mobile?: string|null, email?: string|null, order_number?: string|null, message: string, website?: string|null}  $input
     * @return array{inquiry: Inquiry, replayed: bool}
     *
     * @throws JsonException
     */
    public function create(
        ?User $user,
        array $input,
        Request $request,
    ): array {
        $mobile = trim((string) ($input['mobile'] ?? ''));
        $mobile = $mobile === '' ? null : IranMobile::normalize($mobile);
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $email = $email === '' ? null : $email;
        $orderNumber = strtoupper(trim((string) ($input['order_number'] ?? '')));
        $orderNumber = $orderNumber === '' ? null : $orderNumber;
        $ipHmac = hash_hmac(
            'sha256',
            (string) $request->ip(),
            (string) config('app.key'),
        );
        $userAgent = trim((string) $request->userAgent());
        $duplicateHash = hash('sha256', json_encode([
            'type' => $input['type'],
            'name' => trim($input['name']),
            'mobile' => $mobile,
            'email' => $email,
            'order_number' => $orderNumber,
            'message' => trim($input['message']),
            'ip_hmac' => $ipHmac,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $deduplicationKey = hash(
            'sha256',
            $duplicateHash.'|'.intdiv(now()->getTimestamp(), 600),
        );

        return DB::transaction(function () use (
            $user,
            $input,
            $request,
            $mobile,
            $email,
            $orderNumber,
            $ipHmac,
            $userAgent,
            $duplicateHash,
            $deduplicationKey,
        ): array {
            $existing = Inquiry::query()
                ->where('duplicate_hash', $duplicateHash)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Inquiry) {
                return ['inquiry' => $existing, 'replayed' => true];
            }

            $inquiry = Inquiry::query()->createOrFirst(
                ['deduplication_key' => $deduplicationKey],
                [
                    'user_id' => $user?->id,
                    'type' => $input['type'],
                    'name' => trim($input['name']),
                    'mobile' => $mobile,
                    'email' => $email,
                    'order_number' => $orderNumber,
                    'message' => trim($input['message']),
                    'status' => InquiryStatus::New,
                    'ip_hmac' => $ipHmac,
                    'user_agent_hash' => $userAgent === '' ? null : hash('sha256', $userAgent),
                    'duplicate_hash' => $duplicateHash,
                ],
            );

            if (! $inquiry->wasRecentlyCreated) {
                return ['inquiry' => $inquiry, 'replayed' => true];
            }

            $this->audit->record(
                'inquiry.created',
                actor: $user,
                auditable: $inquiry,
                metadata: [
                    'type' => $inquiry->type,
                    'has_order_number' => $inquiry->order_number !== null,
                    'has_authenticated_user' => $user !== null,
                ],
                request: $request,
            );

            return ['inquiry' => $inquiry, 'replayed' => false];
        }, 3);
    }

    public function updateStatus(
        User $administrator,
        Inquiry $inquiry,
        InquiryStatus $status,
        Request $request,
    ): Inquiry {
        return DB::transaction(function () use (
            $administrator,
            $inquiry,
            $status,
            $request,
        ): Inquiry {
            $locked = Inquiry::query()->whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => $status,
                'assigned_to' => $administrator->id,
                'resolved_at' => in_array(
                    $status,
                    [InquiryStatus::Resolved, InquiryStatus::Closed],
                    true,
                ) ? now() : null,
            ])->save();

            $this->audit->record(
                'inquiry.status_changed',
                actor: $administrator,
                auditable: $locked,
                metadata: ['status' => $status->value],
                request: $request,
            );

            $locked->load(['user', 'assignee']);

            return $locked;
        }, 3);
    }

    /** @return array<string, mixed> */
    public function adminPayload(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'type' => $inquiry->type,
            'name' => $inquiry->name,
            'mobile' => $inquiry->mobile,
            'email' => $inquiry->email,
            'order_number' => $inquiry->order_number,
            'message' => $inquiry->message,
            'status' => $inquiry->status->value,
            'user_id' => $inquiry->user_id,
            'assigned_to' => $inquiry->assigned_to,
            'resolved_at' => $inquiry->resolved_at?->toIso8601String(),
            'created_at' => $inquiry->created_at?->toIso8601String(),
        ];
    }
}
