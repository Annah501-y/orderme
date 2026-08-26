<?php

namespace App\Http\Controllers\Api;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSellerProfileRequest;
use Illuminate\Http\JsonResponse;

class SellerProfileController extends Controller
{
    public function store(StoreSellerProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile already exists.',
            ], 409);
        }

        $sellerProfile = $user->sellerProfile()->create([
            'store_name' => $request->store_name,
            'store_description' => $request->store_description,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => SellerStatus::PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller profile submitted successfully.',
            'data' => [
                'seller_profile' => $sellerProfile,
            ],
        ], 201);
    }

    public function show(): JsonResponse
    {
        $sellerProfile = request()->user()->sellerProfile;

        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'seller_profile' => $sellerProfile,
            ],
        ]);
    }
}