<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerProfileRequest;
use App\Models\SellerProfile;
use App\Services\AddressGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SellerProfileController extends Controller
{
    /**
     * Create or update the authenticated seller's profile.
     */
    public function store(
        StoreSellerProfileRequest $request,
        AddressGeocodingService $geocodingService
    ): JsonResponse {
        $user = $request->user();

        $validated = $request->validated();
        $addressData = [
            'address_line' => $validated['address_line'],
            'district' => $validated['district'],
            'city' => $validated['city'],
            'region' => $validated['region'],
            'country' => $validated['country'] ?? 'Tanzania',
        ];

        try {
            $coordinates = $geocodingService->geocode($addressData);
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
                'message' => 'We could not find that store address. Please check it and try again.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Seller Profile
        |--------------------------------------------------------------------------
        */

        $existingProfile = $user->sellerProfile;
        $businessLicensePath = $validated['business_license']->store(
            'seller-licenses',
            'local'
        );
        $profile = SellerProfile::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'store_name' => $validated['store_name'],
                'store_description' => $validated['store_description'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'nida_number' => $validated['nida_number'],
                'tin_reference' => $validated['tin_reference'],
                'business_license_path' => $businessLicensePath,
                'status' => $existingProfile?->status === 'approved' ? 'approved' : 'pending',
                'rejection_reason' => null,
            ]
        );

        $payoutDestination = $user->payoutDestination()->firstOrNew();
        $payoutDestination->payout_method = $validated['payout_method'];
        $payoutDestination->mobile_phone = $validated['mobile_phone'] ?? null;
        $payoutDestination->bank_bic = $validated['bank_bic'] ?? null;
        $payoutDestination->bank_account_number = $validated['bank_account_number'] ?? null;
        $payoutDestination->bank_account_name = $validated['bank_account_name'] ?? null;
        $payoutDestination->verified_at = null;
        $payoutDestination->verification_code_hash = null;
        $payoutDestination->verification_sent_at = null;
        $payoutDestination->verification_expires_at = null;
        $payoutDestination->verification_attempts = 0;
        $payoutDestination->save();

        $user->assignRole('seller');

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
                ...$addressData,
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'place_id' => $coordinates['place_id'],
            ]);
        } else {
            $address = $user->addresses()->create([
                ...$addressData,
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'place_id' => $coordinates['place_id'],
                'is_default' => true,
            ]);
        }

        if (
            $existingProfile?->business_license_path &&
            $existingProfile->business_license_path !== $businessLicensePath
        ) {
            Storage::disk('local')->delete($existingProfile->business_license_path);
        }

        return response()->json([
            'success' => true,
            'message' => $profile->status === 'approved'
                ? 'Seller profile updated successfully.'
                : 'Seller application submitted for review.',
            'data' => [
                'seller_profile' => $profile,
                'address' => $address,
                'payout_method' => $payoutDestination->payout_method,
            ],
        ], 200);
    }

    /**
     * Get the authenticated seller's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['buyer', 'seller'])) {
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
