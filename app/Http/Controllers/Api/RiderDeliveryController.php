<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderDeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rider')) {
            return response()->json([
                'success' => false,
                'message' => 'Only rider accounts can access deliveries.',
            ], 403);
        }

        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        $deliveries = Delivery::query()
            ->where('rider_id', $rider->id)
            ->with([
                'order',
                'deliveries_stops.sellerOrder.seller',
            ])
            ->latest('assigned_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }
}