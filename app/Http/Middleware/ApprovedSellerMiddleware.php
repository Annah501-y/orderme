<?php

namespace App\Http\Middleware;

use App\Enums\SellerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedSellerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $sellerProfile = $user->sellerProfile;

        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 403);
        }

        if ($sellerProfile->status !== SellerStatus::APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Your seller account has not been approved.',
            ], 403);
        }

        return $next($request);
    }
}