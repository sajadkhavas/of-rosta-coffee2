<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
use App\Exceptions\NotificationDeliveryUnavailable;
use App\Models\NotificationOutbox;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\SubOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class NotificationOutboxService
{
    public function __construct(
        private readonly SmsProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueOrder(
        Order $order,
        string $templateKey,
        array $payload = [],
        ?SubOrder $subOrder = null,
        ?string $deduplicationKey = null,
    ): NotificationOutbox {
        $order->loadMissing(['user', 'roastery', 'subOrder']);
        $subOrder ??= $order->subOrder;
        $attributes = [
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'sub_order_id' => $subOrder?->id,
            'channel' => 'sms',
            'destination' => $order->user->mobile,
            'template_key' => $templateKey,
            'payload' => [
                'order_number' => $order->order_number,
                'roastery_name' => $order->roastery->name,
                ...$payload,
            ],
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

    public function dispatchPending(int $limit = 50): int
    {
        if (! $this->providers->ready()) {
            return 0;
        }

        NotificationOutbox::query()
            ->where('status', NotificationStatus::Processing->value)
            ->where('processing_at', '<=', now()->subMinutes(10))
            ->update([
                'status' => NotificationStatus::Failed->value,
                'processing_at' => null,
                'last_error' => 'stale_processing_outcome_unknown',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        $ids = NotificationOutbox::query()
            ->ready()
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->pluck('id');
        $sent = 0;

        foreach ($ids as $id) {
            if ($this->dispatchOne((string) $id)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function dispatchOne(string $id): bool
    {
        if (! $this->providers->ready()) {
            return false;
        }

        $notification = DB::transaction(function () use ($id): ?NotificationOutbox {
            $locked = NotificationOutbox::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();
            if (
                ! $locked
                || $locked->status !== NotificationStatus::Pending
                || $locked->available_at->isFuture()
            ) {
                return null;
            }

            $locked->forceFill([
                'status' => NotificationStatus::Processing,
                'attempts' => $locked->attempts + 1,
                'provider' => strtolower(trim((string) config(
                    'rosta.notifications.sms_provider',
                    'disabled',
                ))),
                'processing_at' => now(),
            ])->save();

            return $locked;
        }, 3);

        if (! $notification) {
            return false;
        }

        $providerAccepted = false;

        try {
            $template = NotificationTemplate::query()
                ->where('key', $notification->template_key)
                ->where('channel', 'sms')
                ->where('is_active', true)
                ->first();
            if (! $template) {
                throw new RuntimeException('قالب اعلان فعال پیدا نشد.');
            }

            $provider = $this->providers->current();
            $message = $template->render($notification->payload ?? []);
            if ($message === '') {
                throw new RuntimeException('متن اعلان پس از Render خالی است.');
            }

            $providerMessageId = $provider->send(
                $notification->destination,
                $message,
                $template->provider_template,
            );
            $providerAccepted = true;

            DB::transaction(function () use (
                $notification,
                $provider,
                $providerMessageId,
            ): void {
                $locked = NotificationOutbox::query()
                    ->whereKey($notification->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === NotificationStatus::Sent) {
                    return;
                }

                if ($locked->status !== NotificationStatus::Processing) {
                    throw new RuntimeException('وضعیت Outbox هنگام ثبت ارسال معتبر نیست.');
                }

                $locked->forceFill([
                    'status' => NotificationStatus::Sent,
                    'provider' => $provider->name(),
                    'provider_message_id' => $providerMessageId,
                    'last_error' => null,
                    'processing_at' => null,
                    'sent_at' => now(),
                    'failed_at' => null,
                ])->save();
            }, 3);

            return true;
        } catch (Throwable $exception) {
            $failure = $providerAccepted
                ? new NotificationDeliveryUnavailable(
                    'provider_accepted_persistence_unknown',
                    ambiguous: true,
                )
                : $exception;
            $this->recordFailure($notification, $failure);

            return false;
        }
    }

    private function recordFailure(
        NotificationOutbox $notification,
        Throwable $exception,
    ): void {
        DB::transaction(function () use ($notification, $exception): void {
            $locked = NotificationOutbox::query()
                ->whereKey($notification->id)
                ->lockForUpdate()
                ->first();
            if (! $locked || $locked->status === NotificationStatus::Sent) {
                return;
            }

            $maximum = max(1, (int) config('rosta.notifications.max_attempts', 5));
            $providerFailure = $exception instanceof NotificationDeliveryUnavailable
                ? $exception
                : null;
            $retryable = $providerFailure?->retryable === true
                && $providerFailure?->ambiguous === false;
            $terminal = ! $retryable || $locked->attempts >= $maximum;
            $reasonCode = $providerFailure?->reasonCode ?? 'notification_payload_invalid';
            $retryBase = max(
                30,
                $providerFailure?->retryAfterSeconds
                    ?? (int) config('rosta.notifications.retry_seconds', 60),
            );
            $retryDelay = min(
                3600,
                ($retryBase * max(1, $locked->attempts))
                    + random_int(0, max(1, intdiv($retryBase, 4))),
            );
            $locked->forceFill([
                'status' => $terminal
                    ? NotificationStatus::Failed
                    : NotificationStatus::Pending,
                'last_error' => $reasonCode,
                'processing_at' => null,
                'available_at' => $terminal
                    ? $locked->available_at
                    : now()->addSeconds($retryDelay),
                'failed_at' => $terminal ? now() : null,
            ])->save();
        }, 3);
    }
}
