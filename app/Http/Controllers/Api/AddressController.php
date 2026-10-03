<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Services\AddressGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses,
        ]);
    }

    public function store(
        StoreAddressRequest $request,
        AddressGeocodingService $geocodingService
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $coordinates = $geocodingService->geocode($validated);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        if (! $coordinates) {
            return response()->json([
                'success' => false,
                'message' => 'We could not find that address. Please check it and try again.',
            ], 422);
        }

        $isDefault = $request->boolean('is_default');

        // If this is the first address, automatically make it default.
        if (! $user->addresses()->exists()) {
            $isDefault = true;
        }

        // Only one address should be default.
        if ($isDefault) {
            $user->addresses()->update([
                'is_default' => false,
            ]);
        }

        $address = $user->addresses()->create([
            'address_line' => $validated['address_line'],
            'district' => $validated['district'],
            'city' => $validated['city'],
            'region' => $validated['region'],
            'country' => $validated['country'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'place_id' => $coordinates['place_id'],
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address added successfully.',
            'data' => $address,
        ], 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'data' => $address,
        ]);
    }

    public function update(
        StoreAddressRequest $request,
        Address $address,
        AddressGeocodingService $geocodingService
    ): JsonResponse {
        if ($address->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validated();

        try {
            $coordinates = $geocodingService->geocode($validated);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        if (! $coordinates) {
            return response()->json([
                'success' => false,
                'message' => 'We could not find that address. Please check it and try again.',
            ], 422);
        }

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update([
                    'is_default' => false,
                ]);
        }

        $address->update([
            'address_line' => $validated['address_line'],
            'district' => $validated['district'],
            'city' => $validated['city'],
            'region' => $validated['region'],
            'country' => $validated['country'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'place_id' => $coordinates['place_id'],
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => $address->fresh(),
        ]);
    }

    public function destroy(
        Request $request,
        Address $address
    ): JsonResponse {
        if ($address->user_id !== $request->user()->id) {
            abort(404);
        }

        // Don't allow the only/default address to be deleted
        // without first selecting another address.
        if ($address->is_default) {
            $otherAddress = $request->user()
                ->addresses()
                ->where('id', '!=', $address->id)
                ->latest()
                ->first();

            if ($otherAddress) {
                $otherAddress->update([
                    'is_default' => true,
                ]);
            }
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    public function setDefault(
        Request $request,
        Address $address
    ): JsonResponse {
        if ($address->user_id !== $request->user()->id) {
            abort(404);
        }

        $request->user()->addresses()->update([
            'is_default' => false,
        ]);

        $address->update([
            'is_default' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Default address updated successfully.',
            'data' => $address->fresh(),
        ]);
    }
}
