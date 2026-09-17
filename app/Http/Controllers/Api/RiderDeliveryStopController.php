<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliveryStopRequest;
use App\Models\Deliveries_stop;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;

class RiderDeliveryStopController extends Controller
{
    public function update(
        UpdateDeliveryStopRequest $request,
        Deliveries_stop $stop
    ): JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Find rider profile
        |--------------------------------------------------------------------------
        */
        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure this delivery belongs to this rider
        |--------------------------------------------------------------------------
        */
        $delivery = $stop->delivery;

        if (! $delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found for this stop.',
            ], 404);
        }

        if ((int) $delivery->rider_id !== (int) $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this delivery.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery must have started
        |--------------------------------------------------------------------------
        */
        if ($delivery->status !== 'started') {
            return response()->json([
                'success' => false,
                'message' => 'The delivery must be started before managing stops.',
            ], 422);
        }

        $newStatus = $request->validated()['status'];

        /*
        |--------------------------------------------------------------------------
        | Mark stop as arrived
        |--------------------------------------------------------------------------
        */
        if ($newStatus === 'arrived') {

            if ($stop->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending stops can be marked as arrived.',
                ], 422);
            }

            $stop->update([
                'status' => 'arrived',
                'arrived_at' => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Complete stop
        |--------------------------------------------------------------------------
        */
        if ($newStatus === 'completed') {

            if ($stop->status !== 'arrived') {
                return response()->json([
                    'success' => false,
                    'message' => 'The stop must be marked as arrived before it can be completed.',
                ], 422);
            }

            $stop->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update seller order after pickup is completed
            |--------------------------------------------------------------------------
            */
            if (
                $stop->stop_type === 'pickup' &&
                $stop->seller_order_id
            ) {
                $sellerOrder = $stop->sellerOrder;

                if ($sellerOrder) {
                    $sellerOrder->update([
                        'status' => 'picked_up',
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Reload stop
        |--------------------------------------------------------------------------
        */
        $stop->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Delivery stop updated successfully.',
            'data' => [
                'stop_id' => $stop->id,
                'delivery_id' => $stop->delivery_id,
                'seller_order_id' => $stop->seller_order_id,
                'stop_type' => $stop->stop_type,
                'sequence' => $stop->sequence,
                'status' => $stop->status,
                'arrived_at' => $stop->arrived_at,
                'completed_at' => $stop->completed_at,
            ],
        ]);
    }
}