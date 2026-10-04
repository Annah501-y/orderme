<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliveries_stop;
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
                'order.deliveries',
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
            $customerAddress = $order?->address;
            $orderDeliveries = $order?->deliveries
                ?->reject(fn (Delivery $orderDelivery): bool => $orderDelivery->status === 'cancelled')
                ->sortBy('id')
                ->values() ?? collect();
            $orderDeliveryPosition = $orderDeliveries->search(
                fn (Delivery $orderDelivery): bool => (int) $orderDelivery->id === (int) $delivery->id
            );

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

            $stops = $stops->map(function (array $stop, int $index) use ($stops): array {
                $origin = $index > 0
                    ? $stops[$index - 1]['location']
                    : null;

                $stop['location']['navigation_url'] = $this->navigationUrl(
                    $origin,
                    $stop['location']
                );

                return $stop;
            });

            $customerStop = $delivery->deliveries_stops->first(function (Deliveries_stop $stop): bool {
                return $stop->stop_type === 'delivery' && $stop->seller_order_id === null;
            });

            /*
            |--------------------------------------------------------------------------
            | Add customer as the final delivery destination
            |--------------------------------------------------------------------------
            */
            $deliverySequence = $stops->count() + 1;
            $deliveryLocation = $customerAddress ? [
                'latitude' => $customerAddress->latitude,
                'longitude' => $customerAddress->longitude,
            ] : null;

            $stops->push([
                'stop_id' => $customerStop?->id,
                'sequence' => $deliverySequence,
                'type' => 'delivery',
                'status' => $customerStop?->status ?? 'pending',

                'seller' => null,

                'seller_order' => null,

                // Guest orders have no user record; expose their saved order contact to the rider.
                'customer' => ($customer || $order?->guest_name) ? [
                    'id' => $customer?->id,
                    'name' => $customer->name ?? $order?->guest_name,
                    'phone' => $order?->guest_phone ?? $customer?->phone ?? $customerAddress?->phone,
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
                    'navigation_url' => $this->navigationUrl(
                        $stops->last()['location'] ?? null,
                        $deliveryLocation
                    ),
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
                'order_delivery_number' => $orderDeliveryPosition === false ? null : $orderDeliveryPosition + 1,
                'order_delivery_count' => $orderDeliveries->count(),

                'status' => $delivery->status,

                'assigned_at' => $delivery->assigned_at,
                'accepted_at' => $delivery->accepted_at,
                'started_at' => $delivery->started_at,
                'completed_at' => $delivery->completed_at,

                // Keep guest contact details available in the rider's order detail response.
                'customer' => ($customer || $order?->guest_name) ? [
                    'id' => $customer?->id,
                    'name' => $customer->name ?? $order?->guest_name,
                    'phone' => $order?->guest_phone ?? $customer?->phone ?? $customerAddress?->phone,
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

    /**
     * Build a Google Maps directions link from the previous stop to this stop.
     *
     * @param  array<string, mixed>|null  $origin
     * @param  array{latitude: mixed, longitude: mixed}|null  $destination
     */
    private function navigationUrl(?array $origin, ?array $destination): ?string
    {
        if (
            ! $destination ||
            $destination['latitude'] === null ||
            $destination['longitude'] === null
        ) {
            return null;
        }

        $query = [
            'api' => 1,
            'destination' => $destination['latitude'].','.$destination['longitude'],
            'travelmode' => 'driving',
        ];

        if (
            $origin &&
            isset($origin['latitude'], $origin['longitude'])
        ) {
            $query['origin'] = $origin['latitude'].','.$origin['longitude'];
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($query);
    }
}
