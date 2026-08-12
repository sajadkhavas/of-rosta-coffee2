<?php

namespace App\Services\Kavenegar;

use Illuminate\Support\Facades\Cache;

final class KavenegarCircuitBreaker
{
    private const FAILURES_KEY = 'rosta:kavenegar:circuit:failures';

    private const OPEN_KEY = 'rosta:kavenegar:circuit:open-until';

    public function isOpen(): bool
    {
        return $this->retryAfterSeconds() > 0;
    }

    public function retryAfterSeconds(): int
    {
        $openUntil = (int) Cache::get(self::OPEN_KEY, 0);

        return max(0, $openUntil - now()->getTimestamp());
    }

    public function recordFailure(): void
    {
        $window = max(30, (int) config('rosta.kavenegar.circuit_window_seconds', 300));
        Cache::add(self::FAILURES_KEY, 0, now()->addSeconds($window));
        $failures = (int) Cache::increment(self::FAILURES_KEY);
        $threshold = max(2, (int) config('rosta.kavenegar.circuit_failure_threshold', 5));

        if ($failures < $threshold) {
            return;
        }

        $openSeconds = max(30, (int) config('rosta.kavenegar.circuit_open_seconds', 120));
        Cache::put(
            self::OPEN_KEY,
            now()->addSeconds($openSeconds)->getTimestamp(),
            now()->addSeconds($openSeconds),
        );
    }

    public function recordSuccess(): void
    {
        Cache::forget(self::FAILURES_KEY);
        Cache::forget(self::OPEN_KEY);
    }
}
