<?php

return [
    'log_channel' => env('ROSTA_OBSERVABILITY_LOG_CHANNEL', 'stack'),
    'queues' => array_values(array_filter(array_map(
        static fn (string $queue): string => trim($queue),
        explode(',', (string) env('ROSTA_OBSERVABILITY_QUEUES', 'default,notifications,media')),
    ))),
    'max_failed_jobs' => max(0, (int) env('ROSTA_MAX_FAILED_JOBS', 25)),
    'max_failed_job_age_seconds' => max(60, (int) env('ROSTA_MAX_FAILED_JOB_AGE_SECONDS', 86_400)),
    'max_queue_depth' => max(1, (int) env('ROSTA_MAX_QUEUE_DEPTH', 1_000)),
];
