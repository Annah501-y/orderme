<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderDeliveryController extends Controller
{
    /**
     * Display all deliveries assigned to the authenticated rider.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        $deliveries = Delivery::where('rider_id', $rider->id)
            ->with([
                'order.user',
                'deliveries_stops.sellerOrder.seller',
                'deliveries_stops.sellerOrder.order.items.product',
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Rider deliveries retrieved successfully.',
            'data' => $deliveries,
        ]);
    }

    /**
     * Allow the rider to accept or start an assigned delivery.
     */
    public function update(
        Request $request,
        Delivery $delivery
    ): JsonResponse {
        $user = $request->user();

        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        if ((int) $delivery->rider_id !== (int) $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this delivery.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:accepted,started',
            ],
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'accepted') {
            if ($delivery->status !== 'assigned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only assigned deliveries can be accepted.',
                ], 422);
            }

            $delivery->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        }

        if ($newStatus === 'started') {
            if ($delivery->status !== 'accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'The delivery must be accepted before it can be started.',
                ], 422);
            }

            $delivery->update([
                'status' => 'started',
                'started_at' => now(),
            ]);
        }

        $delivery->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Delivery status updated successfully.',
            'data' => [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'rider_id' => $delivery->rider_id,
                'status' => $delivery->status,
                'assigned_at' => $delivery->assigned_at,
                'accepted_at' => $delivery->accepted_at,
                'started_at' => $delivery->started_at,
                'completed_at' => $delivery->completed_at,
            ],
        ]);
    }
}