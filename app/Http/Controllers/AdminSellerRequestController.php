<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSellerRequestController extends Controller {
    /**
    * Get all seller applications.
    */

    public function index( Request $request ): JsonResponse {
        $query = SellerProfile::with( [
            'user',
            'user.addresses',
        ] )->latest();

        if ( $request->filled( 'status' ) ) {
            $query->where( 'status', $request->status );
        }

        $sellerProfiles = $query->get();

        return response()->json( [
            'success' => true,
            'data' => [
                'seller_requests' => $sellerProfiles,
            ],
        ] );
    }

    /**
    * Get one seller application.
    */

    public function show( SellerProfile $sellerProfile ): JsonResponse {
        $sellerProfile->load( [
            'user',
            'user.addresses',
        ] );

        return response()->json( [
            'success' => true,
            'data' => [
                'seller_request' => $sellerProfile,
            ],
        ] );
    }

    /**
    * Approve a seller application.
    */

    public function approve(
        SellerProfile $sellerProfile
    ): JsonResponse {
        if ( $sellerProfile->status === 'approved' ) {
            return response()->json( [
                'success' => false,
                'message' => 'This seller application is already approved.',
            ], 422 );
        }
        if ( !$sellerProfile->user->addresses()->exists() ) {
            return response()->json( [
                'success'=>false,
                'message'=>'your profile cannot be approved untill a store address has been added.',
            ], 422 );
        }

        $sellerProfile->update( [
            'status' => 'approved',
            'rejection_reason' => null,
        ] );

        return response()->json( [
            'success' => true,
            'message' => 'Seller application approved successfully.',
            'data' => [
                'seller_profile' => $sellerProfile->fresh()->load( 'user' ),
            ],
        ] );
    }

    /**
    * Reject a seller application.
    */

    public function reject(
        Request $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        $validated = $request->validate( [
            'rejection_reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ], [
            'rejection_reason.required' =>
            'Please provide a reason for rejecting this seller application.',
            'rejection_reason.min' =>
            'The rejection reason must be at least 5 characters.',
            'rejection_reason.max' =>
            'The rejection reason cannot exceed 1000 characters.',
        ] );

        if ( $sellerProfile->status === 'approved' ) {
            return response()->json( [
                'success' => false,
                'message' => 'An approved seller cannot be rejected.',
            ], 422 );
        }

        $sellerProfile->update( [
            'status' => 'rejected',
            'rejection_reason' => $validated[ 'rejection_reason' ],
        ] );

        return response()->json( [
            'success' => true,
            'message' => 'Seller application rejected.',
            'data' => [
                'seller_profile' => $sellerProfile->fresh()->load( 'user' ),
            ],
        ] );
    }
}