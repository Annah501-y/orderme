<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerProfileRequest;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerProfileController extends Controller
{
    /**
     * Create or update the authenticated seller's profile.
     */
    public function store(StoreSellerProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only seller accounts can manage a seller profile.',
            ], 403);
        }

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Seller Profile
        |--------------------------------------------------------------------------
        */

        $profile = SellerProfile::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'store_name' => $validated['store_name'],
                'store_description' => $validated['store_description'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Seller Store Address
        |--------------------------------------------------------------------------
        */

        $address = $user->addresses()
            ->where('is_default', true)
            ->first();

        if ($address) {
            $address->update([
                'address_line' => $validated['address_line'],
                'district' => $validated['district'],
                'city' => $validated['city'],
                'region' => $validated['region'],
                'country' => $validated['country'] ?? 'Tanzania',
            ]);
        } else {
            $address = $user->addresses()->create([
                'address_line' => $validated['address_line'],
                'district' => $validated['district'],
                'city' => $validated['city'],
                'region' => $validated['region'],
                'country' => $validated['country'] ?? 'Tanzania',
                'is_default' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Seller profile updated successfully.',
            'data' => [
                'seller_profile' => $profile,
                'address' => $address,
            ],
        ], 200);
    }

    /**
     * Get the authenticated seller's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only seller accounts can access a seller profile.',
            ], 403);
        }

        $profile = $user->sellerProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 404);
        }

        $address = $user->addresses()
            ->where('is_default', true)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'seller_profile' => $profile,
                'address' => $address,
            ],
        ]);
    }
}