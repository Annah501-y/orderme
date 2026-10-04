<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Deliveries_stop;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDeliveryController extends Controller
{
    /**
     * List ready seller orders that have not been assigned to a rider.
     */
    public function readyForAssignment(): JsonResponse
    {
        $sellerOrders = SellerOrder::query()
            ->with([
                'seller.addresses' => function ($query): void {
                    $query->where('is_default', true)->limit(1);
                },
                'order.user',
            ])
            ->where('status', 'ready_for_delivery')
            ->whereDoesntHave('deliveries_stops')
            ->oldest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Ready seller orders retrieved successfully.',
            'data' => $sellerOrders,
        ]);
    }

    public function index(): JsonResponse
    {
        $deliveries = Delivery::with([
            'order.user',
            'rider.user',
            'otp',

            'deliveries_stops.sellerOrder.seller',

            'deliveries_stops.sellerOrder.order.items.product',
        ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Deliveries retrieved successfully.',
            'data' => $deliveries,
        ]);
    }

    /**
     * Create a delivery and assign it to a rider.
     */
    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::find($validated['order_id']);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $customerAddress = $order->address;

        if (
            ! $customerAddress
            || $customerAddress->latitude === null
            || $customerAddress->longitude === null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'The customer order needs a delivery address with GPS coordinates before rider assignment.',
            ], 422);
        }
        $payment = $order->payments()
            ->where('status', 'paid')
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'This order can not be assigned for delivery',
            ]);
        }

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
        )
            ->where('order_id', $order->id)
            ->get();

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
                $stop['stop_type'] === 'delivery'
                && ! empty($stop['seller_order_id'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'The customer delivery stop cannot be linked to a seller order.',
                ], 422);
            }

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

        $customerDestinationCount = collect($validated['stops'])
            ->where('stop_type', 'delivery')
            ->count();

        if ($customerDestinationCount !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Include one customer destination; its saved address will be used for the delivery stop.',
            ], 422);
        }

        $pickupSellerOrderIds = collect($validated['stops'])
            ->where('stop_type', 'pickup')
            ->pluck('seller_order_id')
            ->filter()
            ->map(fn ($sellerOrderId): int => (int) $sellerOrderId)
            ->unique()
            ->sort()
            ->values();
        $pickupStopCount = collect($validated['stops'])
            ->where('stop_type', 'pickup')
            ->count();

        $requestedSellerOrderIds = $sellerOrders
            ->pluck('id')
            ->map(fn ($sellerOrderId): int => (int) $sellerOrderId)
            ->sort()
            ->values();

        if (
            $pickupStopCount !== $sellerOrders->count()
            || $pickupSellerOrderIds->all() !== $requestedSellerOrderIds->all()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Add exactly one pickup stop for each selected seller order.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE DELIVERY
        |--------------------------------------------------------------------------
        */

        $delivery = DB::transaction(function () use ($validated, $rider, $customerAddress) {

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

            $pickupStops = array_values(array_filter(
                $validated['stops'],
                fn (array $stop): bool => $stop['stop_type'] === 'pickup'
            ));

            foreach ($pickupStops as $stop) {

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

            // Derive the destination from the customer's saved address, not the admin form payload.
            Deliveries_stop::create([
                'delivery_id' => $delivery->id,
                'seller_order_id' => null,
                'is_customer_dropoff' => true,
                'stop_type' => 'delivery',
                'sequence' => (int) collect($pickupStops)->max('sequence') + 1,
                'address' => collect([
                    $customerAddress->address_line,
                    $customerAddress->district,
                    $customerAddress->city,
                    $customerAddress->region,
                    $customerAddress->country,
                ])->filter()->implode(', '),
                'latitude' => $customerAddress->latitude,
                'longitude' => $customerAddress->longitude,
                'status' => 'pending',
            ]);

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
