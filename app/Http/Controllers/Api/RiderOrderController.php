<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderOrderController extends Controller
{
    /**
     * Get deliveries assigned to the logged-in rider.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rider')) {
            return response()->json([
                'success' => false,
                'message' => 'Only rider accounts can access rider orders.',
            ], 403);
        }

        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        $deliveries = Delivery::where('rider_id', $rider->id)
            ->with([
                'deliveries_stops.sellerOrder.seller',
                'order',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }
}