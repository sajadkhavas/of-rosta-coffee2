<?php

namespace App\Services\Cafe;

use App\Enums\CafeStatus;
use App\Models\Cafe;

final class CafeDirectoryService
{
    /** @return list<array{cafe:Cafe,distance_km:float|null}> */
    public function search(?string $city, ?float $latitude, ?float $longitude, float $radiusKm = 10.0): array
    {
        $query = Cafe::query()
            ->where('status', CafeStatus::Verified->value)
            ->whereNotNull('verified_at');

        if ($city !== null && trim($city) !== '') {
            $query->where('city', trim($city));
        }

        if ($latitude !== null && $longitude !== null) {
            $latDelta = $radiusKm / 111.32;
            $cosine = max(0.01, cos(deg2rad($latitude)));
            $lngDelta = $radiusKm / (111.32 * $cosine);
            $query->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
        }

        /** @var list<array{cafe:Cafe,distance_km:float|null}> $entries */
        $entries = [];
        foreach ($query->orderBy('name')->limit(200)->get() as $cafe) {
            $distance = null;
            if ($latitude !== null && $longitude !== null && $cafe->latitude !== null && $cafe->longitude !== null) {
                $distance = $this->distanceKm($latitude, $longitude, $cafe->latitude, $cafe->longitude);
            }

            if ($distance === null || $distance <= $radiusKm) {
                $entries[] = ['cafe' => $cafe, 'distance_km' => $distance];
            }
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => ($left['distance_km'] ?? PHP_FLOAT_MAX) <=> ($right['distance_km'] ?? PHP_FLOAT_MAX),
        );

        return $entries;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }
}
