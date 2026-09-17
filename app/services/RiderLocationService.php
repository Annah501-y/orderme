<?php

namespace App\Services;

use App\Models\Rider;
use Illuminate\Support\Collection;

class RiderLocationService
{
    /**
     * Find available riders within a given radius.
     */
    public function findNearbyRiders(
        float $latitude,
        float $longitude,
        float $radiusKm = 10
    ): Collection {
        $distanceFormula = '
            6371 * acos(
                LEAST(
                    1,
                    GREATEST(
                        -1,
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )
                )
            )
        ';

        return Rider::query()
            ->where('is_available', true)
            ->where('status', 'online')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('riders.*')
            ->selectRaw(
                "($distanceFormula) AS distance_km",
                [
                    $latitude,
                    $longitude,
                    $latitude,
                ]
            )
            ->orderByRaw(
                "($distanceFormula) ASC",
                [
                    $latitude,
                    $longitude,
                    $latitude,
                ]
            )
            ->get()
            ->filter(function ($rider) use ($radiusKm) {
                return $rider->distance_km <= $radiusKm;
            })
            ->values();
    }
}