<?php

namespace App\Services\Observability;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

final class QueueTelemetry
{
    /** @var array<int, float> */
    private array $startedAt = [];

    public function __construct(
        private readonly OperationalEventLogger $events,
    ) {}

    public function processing(JobProcessing $event): void
    {
        $id = spl_object_id($event->job);
        $this->startedAt[$id] = microtime(true);
        $this->events->info('queue.processing', $this->context($event->job, [
            'metric' => 'rosta.queue.processing.total',
            'trace_id' => $this->traceId($event->job),
        ]));
    }

    public function processed(JobProcessed $event): void
    {
        $this->events->info('queue.processed', $this->context($event->job, [
            'metric' => 'rosta.queue.processed.total',
            'duration_ms' => $this->durationMs($event->job),
            'trace_id' => $this->traceId($event->job),
        ]));
    }

    public function failed(JobFailed $event): void
    {
        $this->events->warning('queue.failed', $this->context($event->job, [
            'metric' => 'rosta.queue.failed.total',
            'duration_ms' => $this->durationMs($event->job),
            'trace_id' => $this->traceId($event->job),
            'exception_class' => $event->exception::class,
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(Job $job, array $extra): array
    {
        return array_merge([
            'connection' => $job->getConnectionName(),
            'queue' => $job->getQueue(),
            'job' => $job->resolveName(),
            'attempt' => $job->attempts(),
        ], $extra);
    }

    private function durationMs(Job $job): ?int
    {
        $id = spl_object_id($job);
        if (! isset($this->startedAt[$id])) {
            return null;
        }

        $duration = max(0, (int) round((microtime(true) - $this->startedAt[$id]) * 1000));
        unset($this->startedAt[$id]);

        return $duration;
    }

    private function traceId(Job $job): string
    {
        return substr(hash('sha256', implode('|', [
            $job->getConnectionName(),
            $job->getQueue(),
            $job->resolveName(),
            (string) spl_object_id($job),
        ])), 0, 32);
    }
}
