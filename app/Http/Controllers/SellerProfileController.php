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
                'message' => 'Only seller accounts can create a seller profile.',
            ], 403);
        }

        $validated = $request->validated();

        $profile = SellerProfile::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'store_name' => $validated['store_name'],
                'store_description' => $validated['store_description'] ?? null,
                'phone' => $validated['phone'] ?? null,

                // A new submission requires admin review.
                'status' => 'pending',
                'rejection_reason' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Seller profile submitted successfully. Your account is now awaiting admin approval.',
            'data' => [
                'seller_profile' => $profile,
            ],
        ], 201);
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

        return response()->json([
            'success' => true,
            'data' => [
                'seller_profile' => $profile,
            ],
        ]);
    }
}