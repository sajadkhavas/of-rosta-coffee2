<?php

namespace App\Services\Seller;

use App\Models\Roastery;
use App\Models\RoasteryClosure;
use App\Models\RoasteryScheduleException;
use App\Models\RoasteryWeeklyHour;
use Carbon\CarbonImmutable;
use DateTimeZone;

final class RoasteryAvailability
{
    /** @return array<string, mixed> */
    public function snapshot(Roastery $roastery, ?CarbonImmutable $at = null): array
    {
        $moment = ($at ?? CarbonImmutable::now('UTC'))->utc();
        $databaseMoment = $moment->setTimezone((string) config('app.timezone', 'UTC'));
        $timezone = $this->timezone($roastery->timezone ?? 'Asia/Tehran');
        $local = $moment->setTimezone($timezone);
        $closure = RoasteryClosure::query()
            ->where('roastery_id', $roastery->id)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $databaseMoment)
            ->where('ends_at', '>', $databaseMoment)
            ->orderBy('starts_at')
            ->first();
        $hasActiveClosure = $closure instanceof RoasteryClosure;
        $operating = $this->isOperatingAt($roastery, $local, $timezone);
        $acceptingOrders = ! ($closure?->blocks_new_orders ?? false);
        $status = $hasActiveClosure
            ? 'temporarily_closed'
            : ($operating ? 'open' : 'outside_hours');

        return [
            'timezone' => $timezone,
            'status' => $status,
            'operating_now' => $operating && ! $hasActiveClosure,
            'accepting_orders' => $acceptingOrders,
            'public_reason' => $closure?->public_reason
                ?? $this->exceptionReason($roastery, $local),
            'closed_until' => $closure?->ends_at?->utc()->toIso8601String(),
            'next_open_at' => $operating && ! $hasActiveClosure
                ? null
                : $this->nextOperatingAt(
                    $roastery,
                    $closure?->ends_at?->utc() ?? $moment,
                    $timezone,
                )?->utc()->toIso8601String(),
            'order_policy' => $acceptingOrders
                ? 'accepting_new_orders'
                : 'new_orders_blocked_by_temporary_closure',
        ];
    }

    private function timezone(string $timezone): string
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : 'Asia/Tehran';
    }

    private function isOperatingAt(
        Roastery $roastery,
        CarbonImmutable $local,
        string $timezone,
    ): bool {
        $configured = RoasteryWeeklyHour::query()
            ->where('roastery_id', $roastery->id)
            ->exists();

        if (! $configured) {
            $exception = RoasteryScheduleException::query()
                ->where('roastery_id', $roastery->id)
                ->whereDate('local_date', $local->toDateString())
                ->first();
            if (! ($exception instanceof RoasteryScheduleException)) {
                return true;
            }
        }

        foreach ([$local->startOfDay(), $local->subDay()->startOfDay()] as $date) {
            $interval = $this->intervalForDate($roastery, $date, $timezone, $configured);
            if ($interval === null) {
                continue;
            }
            [$opens, $closes] = $interval;
            if ($local->greaterThanOrEqualTo($opens) && $local->lessThan($closes)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{CarbonImmutable, CarbonImmutable}|null */
    private function intervalForDate(
        Roastery $roastery,
        CarbonImmutable $date,
        string $timezone,
        bool $configured,
    ): ?array {
        $exception = RoasteryScheduleException::query()
            ->where('roastery_id', $roastery->id)
            ->whereDate('local_date', $date->toDateString())
            ->first();

        if ($exception instanceof RoasteryScheduleException) {
            if ($exception->is_closed || $exception->opens_at === null || $exception->closes_at === null) {
                return null;
            }

            return $this->interval($date, $exception->opens_at, $exception->closes_at, $timezone);
        }

        if (! $configured) {
            return [
                CarbonImmutable::parse($date->toDateString().' 00:00', $timezone),
                CarbonImmutable::parse($date->addDay()->toDateString().' 00:00', $timezone),
            ];
        }

        $hours = RoasteryWeeklyHour::query()
            ->where('roastery_id', $roastery->id)
            ->where('weekday', $date->dayOfWeek)
            ->first();
        if (
            ! ($hours instanceof RoasteryWeeklyHour)
            || $hours->is_closed
            || $hours->opens_at === null
            || $hours->closes_at === null
        ) {
            return null;
        }

        return $this->interval($date, $hours->opens_at, $hours->closes_at, $timezone);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function interval(
        CarbonImmutable $date,
        string $opensAt,
        string $closesAt,
        string $timezone,
    ): array {
        $opens = CarbonImmutable::parse($date->toDateString().' '.$opensAt, $timezone);
        $closes = CarbonImmutable::parse($date->toDateString().' '.$closesAt, $timezone);
        if ($closes->lessThanOrEqualTo($opens)) {
            $closes = $closes->addDay();
        }

        return [$opens, $closes];
    }

    private function nextOperatingAt(
        Roastery $roastery,
        CarbonImmutable $fromUtc,
        string $timezone,
    ): ?CarbonImmutable {
        $local = $fromUtc->setTimezone($timezone);
        $configured = RoasteryWeeklyHour::query()
            ->where('roastery_id', $roastery->id)
            ->exists();

        for ($offset = 0; $offset <= 8; $offset++) {
            $date = $local->startOfDay()->addDays($offset);
            $interval = $this->intervalForDate($roastery, $date, $timezone, $configured);
            if ($interval === null) {
                continue;
            }
            [$opens, $closes] = $interval;
            if ($local->lessThan($opens)) {
                return $opens;
            }
            if ($local->lessThan($closes)) {
                return $local;
            }
        }

        return null;
    }

    private function exceptionReason(Roastery $roastery, CarbonImmutable $local): ?string
    {
        return RoasteryScheduleException::query()
            ->where('roastery_id', $roastery->id)
            ->whereDate('local_date', $local->toDateString())
            ->where('is_closed', true)
            ->value('public_reason');
    }
}
