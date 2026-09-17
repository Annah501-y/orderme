<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Make sure the logged-in user is a rider
        |--------------------------------------------------------------------------
        */
        if (! $user->hasRole('rider')) {
            return response()->json([
                'success' => false,
                'message' => 'Only rider accounts can access rider orders.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Find the rider profile
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
        | Get only deliveries assigned to this rider
        |--------------------------------------------------------------------------
        */
        $deliveries = Delivery::where('rider_id', $rider->id)
            ->with([
                'deliveries_stops.sellerOrder.seller',
                'order.user',
                'order.items.product.seller',
            ])
            ->orderByDesc('created_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Transform deliveries into a rider-friendly response
        |--------------------------------------------------------------------------
        */
        $data = $deliveries->map(function ($delivery) {

            $order = $delivery->order;
            $customer = $order?->user;

            /*
            |--------------------------------------------------------------------------
            | Find customer's default delivery address
            |--------------------------------------------------------------------------
            */
            $customerAddress = null;

            if ($customer) {
                $customerAddress = $customer->addresses()
                    ->where('is_default', true)
                    ->first();

                /*
                | If no default address exists, use the latest address.
                */
                if (! $customerAddress) {
                    $customerAddress = $customer->addresses()
                        ->latest()
                        ->first();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Build pickup stops
            |--------------------------------------------------------------------------
            */
            $stops = $delivery->deliveries_stops
                ->sortBy('sequence')
                ->map(function ($stop) use ($order) {

                    $sellerOrder = $stop->sellerOrder;
                    $seller = $sellerOrder?->seller;

                    /*
                    |--------------------------------------------------------------------------
                    | Find items belonging to this seller order
                    |--------------------------------------------------------------------------
                    */
                    $items = collect();

                    if ($sellerOrder && $order) {
                        $items = $order->items
                            ->filter(function ($item) use ($sellerOrder) {

                                return $item->product
                                    && (int) $item->product->seller_id === (int) $sellerOrder->seller_id;
                            })
                            ->values();
                    }

                    return [
                        'stop_id' => $stop->id,
                        'sequence' => $stop->sequence,
                        'type' => $stop->stop_type,
                        'status' => $stop->status,

                        'seller' => $seller ? [
                            'id' => $seller->id,
                            'name' => $seller->name,
                            'phone' => $seller->phone,
                            'profile_photo' => $seller->profile_photo,
                        ] : null,

                        'seller_order' => $sellerOrder ? [
                            'id' => $sellerOrder->id,
                            'status' => $sellerOrder->status,
                            'seller_total' => $sellerOrder->seller_total,
                        ] : null,

                        'location' => [
                            'address' => $stop->address,
                            'latitude' => $stop->latitude,
                            'longitude' => $stop->longitude,
                        ],

                        'items' => $items->map(function ($item) {
                            return [
                                'order_item_id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $item->product?->name,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total_price' => $item->total_price,
                            ];
                        })->values(),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | Add customer as the final delivery destination
            |--------------------------------------------------------------------------
            */
            $stops->push([
                'stop_id' => null,
                'sequence' => $stops->count() + 1,
                'type' => 'delivery',
                'status' => 'pending',

                'seller' => null,

                'seller_order' => null,

                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ] : null,

                'location' => $customerAddress ? [
                    'address_line' => $customerAddress->address_line,
                    'district' => $customerAddress->district,
                    'city' => $customerAddress->city,
                    'region' => $customerAddress->region,
                    'country' => $customerAddress->country,
                    'full_address' => collect([
                        $customerAddress->address_line,
                        $customerAddress->district,
                        $customerAddress->city,
                        $customerAddress->region,
                        $customerAddress->country,
                    ])->filter()->implode(', '),

                    'latitude' => $customerAddress->latitude,
                    'longitude' => $customerAddress->longitude,

                    'place_id' => $customerAddress->place_id,
                ] : null,

                'items' => [],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Return clean rider delivery data
            |--------------------------------------------------------------------------
            */
            return [
                'delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,

                'status' => $delivery->status,

                'assigned_at' => $delivery->assigned_at,
                'accepted_at' => $delivery->accepted_at,
                'started_at' => $delivery->started_at,
                'completed_at' => $delivery->completed_at,

                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ] : null,

                'customer_address' => $customerAddress ? [
                    'address_line' => $customerAddress->address_line,
                    'district' => $customerAddress->district,
                    'city' => $customerAddress->city,
                    'region' => $customerAddress->region,
                    'country' => $customerAddress->country,
                    'full_address' => collect([
                        $customerAddress->address_line,
                        $customerAddress->district,
                        $customerAddress->city,
                        $customerAddress->region,
                        $customerAddress->country,
                    ])->filter()->implode(', '),

                    'latitude' => $customerAddress->latitude,
                    'longitude' => $customerAddress->longitude,
                    'place_id' => $customerAddress->place_id,
                ] : null,

                'stops' => $stops,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}