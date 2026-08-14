<?php

namespace App\Services\Seller;

use App\Enums\NotificationStatus;
use App\Models\NotificationOutbox;
use App\Models\User;

final class SellerOrganizationNotifier
{
    /** @param array<string, mixed> $payload */
    public function queueMobile(
        string $mobile,
        string $templateKey,
        array $payload,
        ?User $user = null,
        ?string $deduplicationKey = null,
    ): NotificationOutbox {
        $attributes = [
            'user_id' => $user?->id,
            'channel' => 'sms',
            'destination' => $mobile,
            'template_key' => $templateKey,
            'payload' => $payload,
            'status' => NotificationStatus::Pending,
            'provider' => strtolower(trim((string) config(
                'rosta.notifications.sms_provider',
                'disabled',
            ))),
            'deduplication_key' => $deduplicationKey,
            'available_at' => now(),
        ];

        if ($deduplicationKey !== null) {
            return NotificationOutbox::query()->createOrFirst(
                ['deduplication_key' => $deduplicationKey],
                $attributes,
            );
        }

        return NotificationOutbox::query()->create($attributes);
    }
}
