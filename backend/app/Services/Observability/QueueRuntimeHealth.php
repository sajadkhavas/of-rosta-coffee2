<?php

namespace App\Services\Observability;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class QueueRuntimeHealth
{
    /**
     * @return array{
     *   driver: string,
     *   backend_available: bool,
     *   failed_jobs_table: bool,
     *   failed_jobs: int|null,
     *   oldest_failed_age_seconds: int|null,
     *   queues: array<string, int|null>,
     *   degraded: bool,
     *   ready: bool
     * }
     */
    public function snapshot(): array
    {
        $driver = (string) config('queue.default', 'redis');
        $failedJobsTable = Schema::hasTable((string) config('queue.failed.table', 'failed_jobs'));
        $failedJobs = null;
        $oldestFailedAgeSeconds = null;

        if ($failedJobsTable) {
            $table = (string) config('queue.failed.table', 'failed_jobs');
            $failedJobs = DB::table($table)->count();
            $oldest = DB::table($table)->min('failed_at');
            if (is_string($oldest) && $oldest !== '') {
                try {
                    $oldestFailedAgeSeconds = max(
                        0,
                        (int) CarbonImmutable::parse($oldest)->diffInSeconds(now(), true),
                    );
                } catch (Throwable) {
                    $oldestFailedAgeSeconds = null;
                }
            }
        }

        [$backendAvailable, $queues] = $this->queueDepths($driver);
        $maxFailedJobs = (int) config('rosta.observability.max_failed_jobs', 25);
        $maxFailedAge = (int) config('rosta.observability.max_failed_job_age_seconds', 86_400);
        $maxQueueDepth = (int) config('rosta.observability.max_queue_depth', 1_000);

        $degraded = ($failedJobs !== null && $failedJobs > $maxFailedJobs)
            || ($oldestFailedAgeSeconds !== null && $oldestFailedAgeSeconds > $maxFailedAge)
            || collect($queues)->contains(
                static fn (?int $depth): bool => $depth !== null && $depth > $maxQueueDepth,
            );

        return [
            'driver' => $driver,
            'backend_available' => $backendAvailable,
            'failed_jobs_table' => $failedJobsTable,
            'failed_jobs' => $failedJobs,
            'oldest_failed_age_seconds' => $oldestFailedAgeSeconds,
            'queues' => $queues,
            'degraded' => $degraded,
            'ready' => $backendAvailable && $failedJobsTable,
        ];
    }

    /** @return array{0: bool, 1: array<string, int|null>} */
    private function queueDepths(string $driver): array
    {
        $queueNames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $queue): string => trim((string) $queue),
            (array) config('rosta.observability.queues', ['default', 'notifications', 'media']),
        ))));
        $depths = array_fill_keys($queueNames, null);

        if ($driver === 'sync') {
            return [! app()->environment('production'), $depths];
        }

        if ($driver === 'redis') {
            try {
                $connectionName = (string) config('queue.connections.redis.connection', 'default');
                $redis = Redis::connection($connectionName);
                $pong = $redis->command('ping');
                $available = in_array(strtoupper((string) $pong), ['PONG', '1'], true);
                if (! $available) {
                    return [false, $depths];
                }

                foreach ($queueNames as $queue) {
                    $depths[$queue] = (int) $redis->command('llen', ['queues:'.$queue]);
                }

                return [true, $depths];
            } catch (Throwable) {
                return [false, $depths];
            }
        }

        if ($driver === 'database') {
            $table = (string) config('queue.connections.database.table', 'jobs');
            if (! Schema::hasTable($table)) {
                return [false, $depths];
            }

            foreach ($queueNames as $queue) {
                $depths[$queue] = DB::table($table)->where('queue', $queue)->count();
            }

            return [true, $depths];
        }

        return [false, $depths];
    }
}
