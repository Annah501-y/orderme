<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AddressGeocodingService
{
    public function ensureCoordinates(Address $address): bool
    {
        if ($address->latitude !== null && $address->longitude !== null) {
            return true;
        }

        $coordinates = $this->geocode([
            'address_line' => $address->address_line,
            'district' => $address->district,
            'city' => $address->city,
            'region' => $address->region,
            'country' => $address->country,
        ]);

        if (! $coordinates) {
            return false;
        }

        $address->update([
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'place_id' => $coordinates['place_id'],
        ]);

        return true;
    }

    /**
     * Resolve a user-entered address to coordinates using Google Geocoding.
     *
     * @param  array{address_line: string, district: string, city: string, region: string, country: string}  $address
     * @return array{latitude: float, longitude: float, place_id: string|null}|null
     */
    public function geocode(array $address): ?array
    {
        $apiKey = config('services.google.maps_api_key');

        if (! $apiKey) {
            throw new RuntimeException(
                'Address geocoding is not configured. Set GOOGLE_MAPS_API_KEY.'
            );
        }

        $query = collect([
            $address['address_line'],
            $address['district'],
            $address['city'],
            $address['region'],
            $address['country'],
        ])->filter()->implode(', ');

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            [
                'address' => $query,
                'key' => $apiKey,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'The address lookup service is temporarily unavailable.'
            );
        }

        $data = $response->json();

        if (($data['status'] ?? null) === 'ZERO_RESULTS') {
            return null;
        }

        $location = $data['results'][0]['geometry']['location'] ?? null;

        if (($data['status'] ?? null) !== 'OK' || ! $location) {
            throw new RuntimeException(
                'The address lookup service could not resolve the address.'
            );
        }

        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
            'place_id' => $data['results'][0]['place_id'] ?? null,
        ];
    }
}
