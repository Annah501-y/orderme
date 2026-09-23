<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RoutingService
{
    /**
     * Calculate a driving route between two coordinates.
     */
    public function getDrivingRoute(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude
    ): array {
        $coordinates =
            $originLongitude . ',' . $originLatitude .
            ';' .
            $destinationLongitude . ',' . $destinationLatitude;

        $response = Http::timeout(15)
            ->get(
                "https://router.project-osrm.org/route/v1/driving/{$coordinates}",
                [
                    'overview' => 'false',
                    'alternatives' => 'false',
                    'steps' => 'false',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Unable to connect to the routing service.'
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 'Ok') {
            throw new RuntimeException(
                $data['message'] ??
                'No driving route could be found.'
            );
        }

        $route = $data['routes'][0] ?? null;

        if (! $route) {
            throw new RuntimeException(
                'No driving route could be found.'
            );
        }

        $distanceMeters = $route['distance'] ?? null;
        $durationSeconds = $route['duration'] ?? null;

        if ($distanceMeters === null || $durationSeconds === null) {
            throw new RuntimeException(
                'Routing service returned incomplete route data.'
            );
        }

        return [
            'distance_meters' => round($distanceMeters, 2),

            'distance_km' => round(
                $distanceMeters / 1000,
                2
            ),

            'duration_seconds' => round(
                $durationSeconds
            ),

            'duration_minutes' => (int) ceil(
                $durationSeconds / 60
            ),
        ];
    }
}