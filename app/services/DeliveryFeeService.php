<?php

namespace App\Services;

use InvalidArgumentException;

class DeliveryFeeService
{
    private const BASE_DISTANCE_KM = 1;

    private const BASE_FEE = 1500;

    private const BODABODA_RATE = 400;

    private const BAJAJI_RATE = 600;

    public function calculate(
        float $distanceKm,
        string $vehicleType
    ): array {

        if ($distanceKm < 0) {
            throw new InvalidArgumentException(
                'Distance cannot be negative.'
            );
        }

        $vehicleType = strtolower(trim($vehicleType));

        $ratePerKm = match ($vehicleType) {
            'bodaboda' => self::BODABODA_RATE,
            'bajaji' => self::BAJAJI_RATE,

            default => throw new InvalidArgumentException(
                'Invalid delivery vehicle type.'
            ),
        };

        /*
         * First 1 km uses the base fee.
         * Any distance above 1 km uses the vehicle rate.
         */
        if ($distanceKm <= self::BASE_DISTANCE_KM) {
            $deliveryFee = self::BASE_FEE;
        } else {
            $additionalDistance =
                $distanceKm - self::BASE_DISTANCE_KM;

            $deliveryFee =
                self::BASE_FEE +
                ($additionalDistance * $ratePerKm);
        }

        return [
            'vehicle_type' => $vehicleType,

            'distance_km' => round($distanceKm, 2),

            'rate_per_km' => $ratePerKm,

            'base_distance_km' => self::BASE_DISTANCE_KM,

            'base_fee' => self::BASE_FEE,

            'delivery_fee' => round($deliveryFee),
        ];
    }
}