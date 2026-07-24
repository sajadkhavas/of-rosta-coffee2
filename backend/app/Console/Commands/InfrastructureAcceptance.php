<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

final class InfrastructureAcceptance extends Command
{
    protected $signature = 'rosta:infrastructure-acceptance {--json : Print JSON only}';

    protected $description = 'Exercise the isolated MySQL and Redis cache, session and queue contracts';

    public function handle(): int
    {
        $checks = [];
        $token = Str::lower((string) Str::ulid());

        $this->check(
            $checks,
            'database_driver',
            config('database.default') === 'mysql',
            'Default database driver is MySQL',
        );
        $this->check(
            $checks,
            'cache_driver',
            config('cache.default') === 'redis',
            'Default cache store is Redis',
        );
        $this->check(
            $checks,
            'session_driver',
            config('session.driver') === 'redis',
            'Session driver is Redis',
        );
        $this->check(
            $checks,
            'queue_driver',
            config('queue.default') === 'redis',
            'Queue connection is Redis',
        );

        try {
            $value = DB::scalar('select 1');
            $this->check($checks, 'database_query', (int) $value === 1, 'MySQL scalar query succeeds');
        } catch (Throwable) {
            $this->check($checks, 'database_query', false, 'MySQL scalar query succeeds');
        }

        try {
            $pong = Redis::connection()->command('ping');
            $this->check(
                $checks,
                'redis_ping',
                in_array(strtoupper((string) $pong), ['PONG', '1'], true),
                'Redis responds to PING',
            );
        } catch (Throwable) {
            $this->check($checks, 'redis_ping', false, 'Redis responds to PING');
        }

        $cacheKey = 'rosta:r2c:cache:'.$token;
        try {
            Cache::put($cacheKey, $token, 60);
            $observed = Cache::get($cacheKey);
            Cache::forget($cacheKey);
            $this->check(
                $checks,
                'redis_cache_round_trip',
                is_string($observed) && hash_equals($token, $observed),
                'Redis cache writes, reads and deletes an isolated value',
            );
        } catch (Throwable) {
            Cache::forget($cacheKey);
            $this->check(
                $checks,
                'redis_cache_round_trip',
                false,
                'Redis cache writes, reads and deletes an isolated value',
            );
        }

        $sessionId = substr(hash('sha256', 'rosta:r2c:session:'.$token), 0, 40);
        try {
            $session = Session::driver();
            $session->setId($sessionId);
            $session->start();
            $session->put('rosta_r2c_probe', $token);
            $session->save();

            $stored = $session->getHandler()->read($sessionId);
            $session->getHandler()->destroy($sessionId);
            $this->check(
                $checks,
                'redis_session_round_trip',
                is_string($stored) && str_contains($stored, $token),
                'Redis session handler persists and removes an isolated session',
            );
        } catch (Throwable) {
            $this->check(
                $checks,
                'redis_session_round_trip',
                false,
                'Redis session handler persists and removes an isolated session',
            );
        }

        $queueName = 'rosta-r2c-probe-'.$token;
        try {
            $queue = Queue::connection('redis');
            $payload = json_encode([
                'uuid' => $token,
                'displayName' => 'Rosta infrastructure probe',
                'job' => 'RostaInfrastructureProbe',
                'maxTries' => 1,
                'attempts' => 0,
                'data' => ['token' => $token],
            ], JSON_THROW_ON_ERROR);

            $pushedId = $queue->pushRaw($payload, $queueName);
            $job = $queue->pop($queueName);
            $raw = $job?->getRawBody();
            $job?->delete();

            if ($queue instanceof RedisQueue) {
                $queue->clear($queueName);
            }

            $this->check(
                $checks,
                'redis_queue_round_trip',
                $pushedId !== null && is_string($raw) && str_contains($raw, $token),
                'Laravel Redis queue pushes, reserves and deletes an isolated job',
            );
        } catch (Throwable) {
            $this->check(
                $checks,
                'redis_queue_round_trip',
                false,
                'Laravel Redis queue pushes, reserves and deletes an isolated job',
            );
        }

        $ready = ! in_array(false, array_column($checks, 'passed'), true);
        $report = [
            'ready' => $ready,
            'environment' => app()->environment(),
            'contract_version' => config('rosta.contract_version'),
            'generated_at' => gmdate(DATE_ATOM),
            'checks' => $checks,
            'marker' => $ready ? 'ROSTA_R2C_INFRASTRUCTURE_COMPLETE' : null,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            foreach ($checks as $name => $item) {
                $this->line(sprintf(
                    '[%s] %s — %s',
                    $item['passed'] ? 'PASS' : 'FAIL',
                    $name,
                    $item['evidence'],
                ));
            }
            if ($ready) {
                $this->info('ROSTA_R2C_INFRASTRUCTURE_COMPLETE');
            }
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array{passed: bool, evidence: string}>  $checks
     */
    private function check(array &$checks, string $name, bool $passed, string $evidence): void
    {
        $checks[$name] = compact('passed', 'evidence');
    }
}
