<?php

namespace App\Services\Cafe;

use App\Enums\CafeStatus;
use App\Models\Cafe;
use Illuminate\Support\Collection;

final class CafeDirectoryService
{
    /** @return Collection<int,array{cafe:Cafe,distance_km:float|null}> */
    public function search(?string $city, ?float $latitude, ?float $longitude, float $radiusKm = 10.0): Collection
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

        return $query->orderBy('name')->limit(200)->get()
            ->map(function (Cafe $cafe) use ($latitude, $longitude): array {
                $distance = null;
                if ($latitude !== null && $longitude !== null && $cafe->latitude !== null && $cafe->longitude !== null) {
                    $distance = $this->distanceKm($latitude, $longitude, (float) $cafe->latitude, (float) $cafe->longitude);
                }
                return ['cafe' => $cafe, 'distance_km' => $distance];
            })
            ->filter(static fn (array $entry): bool => $entry['distance_km'] === null || $entry['distance_km'] <= $radiusKm)
            ->sortBy(static fn (array $entry): float => $entry['distance_km'] ?? PHP_FLOAT_MAX)
            ->values();
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
