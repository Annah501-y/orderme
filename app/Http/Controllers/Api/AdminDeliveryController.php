<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Delivery;
use App\Models\Deliveries_stop;
use App\Models\Rider;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDeliveryController extends Controller
{
    /**
     * Create a delivery and assign it to a rider.
     */
    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | CHECK RIDER
        |--------------------------------------------------------------------------
        */

        $rider = Rider::find($validated['rider_id']);

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider not found.',
            ], 404);
        }

        if (! $rider->is_available || $rider->status !== 'online') {
            return response()->json([
                'success' => false,
                'message' => 'The selected rider is not currently available.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK SELLER ORDERS
        |--------------------------------------------------------------------------
        */

        $sellerOrders = SellerOrder::whereIn(
            'id',
            $validated['seller_order_ids']
        )->get();

        if ($sellerOrders->count() !== count($validated['seller_order_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'One or more seller orders could not be found.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY SELLER ORDERS BELONG TO THE SAME MAIN ORDER
        |--------------------------------------------------------------------------
        */

        foreach ($sellerOrders as $sellerOrder) {

            if ((int) $sellerOrder->order_id !== (int) $validated['order_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'All seller orders must belong to the selected customer order.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | SELLER MUST BE READY FOR DELIVERY
            |--------------------------------------------------------------------------
            */

            if ($sellerOrder->status !== 'ready_for_delivery') {
                return response()->json([
                    'success' => false,
                    'message' => "Seller order #{$sellerOrder->id} is not ready for delivery.",
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $alreadyAssigned = Deliveries_stop::where(
                'seller_order_id',
                $sellerOrder->id
            )->exists();

            if ($alreadyAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => "Seller order #{$sellerOrder->id} has already been assigned to a delivery.",
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE STOP SELLER ORDERS
        |--------------------------------------------------------------------------
        */

        foreach ($validated['stops'] as $stop) {

            if (
                ! empty($stop['seller_order_id']) &&
                ! in_array(
                    $stop['seller_order_id'],
                    $validated['seller_order_ids']
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Every pickup stop must belong to the selected seller orders.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE DELIVERY
        |--------------------------------------------------------------------------
        */

        $delivery = DB::transaction(function () use ($validated, $rider) {

            $delivery = Delivery::create([
                'order_id' => $validated['order_id'],
                'rider_id' => $rider->id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE DELIVERY STOPS
            |--------------------------------------------------------------------------
            */

            foreach ($validated['stops'] as $stop) {

                Deliveries_stop::create([
                    'delivery_id' => $delivery->id,
                    'seller_order_id' => $stop['seller_order_id'] ?? null,

                    'stop_type' => $stop['stop_type'],
                    'sequence' => $stop['sequence'],

                    'address' => $stop['address'] ?? null,
                    'latitude' => $stop['latitude'] ?? null,
                    'longitude' => $stop['longitude'] ?? null,

                    'status' => 'pending',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE SELLER ORDERS
            |--------------------------------------------------------------------------
            |
            | Once assigned to a delivery, they are no longer waiting
            | for delivery assignment.
            |
            */

            SellerOrder::whereIn(
                'id',
                $validated['seller_order_ids']
            )->update([
                'status' => 'assigned_to_rider',
            ]);

            return $delivery;
        });

        /*
        |--------------------------------------------------------------------------
        | RETURN CREATED DELIVERY
        |--------------------------------------------------------------------------
        */

        $delivery->load([
            'rider',
            'stops.sellerOrder.seller',
            'order',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery assigned to rider successfully.',
            'data' => [
                'delivery' => $delivery,
            ],
        ], 201);
    }
}