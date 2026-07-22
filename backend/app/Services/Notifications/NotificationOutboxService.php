<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
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
            return NotificationOutbox::query()->firstOrCreate(
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
                'status' => NotificationStatus::Pending->value,
                'available_at' => now(),
                'processing_at' => null,
                'last_error' => 'stale_processing_recovered',
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

            return $locked->fresh();
        }, 3);

        if (! $notification) {
            return false;
        }

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

            DB::transaction(function () use (
                $notification,
                $provider,
                $providerMessageId,
            ): void {
                $locked = NotificationOutbox::query()
                    ->whereKey($notification->id)
                    ->lockForUpdate()
                    ->firstOrFail();
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
            $this->recordFailure($notification, $exception);

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
            $terminal = $locked->attempts >= $maximum;
            $locked->forceFill([
                'status' => $terminal
                    ? NotificationStatus::Failed
                    : NotificationStatus::Pending,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'processing_at' => null,
                'available_at' => $terminal
                    ? $locked->available_at
                    : now()->addSeconds(
                        max(30, (int) config('rosta.notifications.retry_seconds', 60))
                        * $locked->attempts,
                    ),
                'failed_at' => $terminal ? now() : null,
            ])->save();
        }, 3);
    }
}
