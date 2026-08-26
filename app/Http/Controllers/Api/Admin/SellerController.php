<?php

namespace App\Http\Controllers\Api\Admin;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;

class SellerController extends Controller {
    public function index(): JsonResponse {
        $sellers = SellerProfile::query()
        ->with( 'user' )
        ->latest()
        ->paginate( 15 );

        return response()->json( [
            'success' => true,
            'data' => $sellers,
        ] );
    }

    public function approve( SellerProfile $seller ): JsonResponse {
        if ( $seller->status === SellerStatus::APPROVED ) {
            return response()->json( [
                'success'=> false,
                'message'=> 'Seller is already approved',
            ], 409 );
        }
        if ( $seller->status === SellerStatus::SUSPENDED ) {
            return response()->json( [
                'success'=> false,
                'message'=> 'a suspended seller cannot be approved directly',
            ], 409 );
        }
        $seller->update( [
            'status'=>SellerStatus::APPROVED,
        ] );
        return response()->json( [
            'success'=> true,
            'message'=> 'Seller approved successful',
            'data'=>[
                'seller_profile'=>$seller->fresh()->load( 'user:id,name,email' ),
            ],
        ] ) ;
    }
}
