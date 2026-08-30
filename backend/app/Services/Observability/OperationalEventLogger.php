<?php

namespace App\Services\Observability;

use App\Support\OperationalContextRedactor;
use Illuminate\Support\Facades\Log;

final class OperationalEventLogger
{
    public function __construct(
        private readonly OperationalContextRedactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function envelope(string $event, array $context = []): array
    {
        return [
            'event' => $event,
            'service' => 'rosta-api',
            'environment' => app()->environment(),
            'occurred_at' => now()->toImmutable()->toIso8601String(),
            'context' => $this->redactor->redact($context),
        ];
    }

    /** @param array<string, mixed> $context */
    public function info(string $event, array $context = []): void
    {
        Log::channel((string) config('rosta.observability.log_channel', 'stack'))
            ->info('rosta.operational_event', $this->envelope($event, $context));
    }

    /** @param array<string, mixed> $context */
    public function warning(string $event, array $context = []): void
    {
        Log::channel((string) config('rosta.observability.log_channel', 'stack'))
            ->warning('rosta.operational_event', $this->envelope($event, $context));
    }
}
