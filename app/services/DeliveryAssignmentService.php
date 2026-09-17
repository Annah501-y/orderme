<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Deliveries_stop;
use App\Models\Rider;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryAssignmentService
{
    public function __construct(
        protected RiderLocationService $riderLocationService
    ) {}

    public function assign(int $orderId): array
    {
        $orderExists = \App\Models\Order::query()
            ->whereKey($orderId)
            ->exists();

        if (! $orderExists) {
            throw new RuntimeException(
                'Order not found.'
            );
        }

        $sellerOrders = SellerOrder::query()
            ->with([
                'seller.addresses' => function ($query) {
                    $query
                        ->where('is_default', true)
                        ->limit(1);
                },
            ])
            ->where('order_id', $orderId)
            ->get();

        if ($sellerOrders->isEmpty()) {
            throw new RuntimeException(
                'No seller orders found for this order.'
            );
        }

        return DB::transaction(function () use ($sellerOrders, $orderId) {
            $groups = [];

            foreach ($sellerOrders as $sellerOrder) {
                $seller = $sellerOrder->seller;

                if (! $seller) {
                    continue;
                }

                $address = $seller->addresses->first();

                if (
                    ! $address ||
                    $address->latitude === null ||
                    $address->longitude === null
                ) {
                    continue;
                }

                $nearbyRiders = $this->riderLocationService
                    ->findNearbyRiders(
                        (float) $address->latitude,
                        (float) $address->longitude,
                        10
                    );

                if ($nearbyRiders->isEmpty()) {
                    continue;
                }

                $rider = $this->findBestRider(
                    $nearbyRiders,
                    $groups
                );

                if (! $rider) {
                    continue;
                }

                if (! isset($groups[$rider->id])) {
                    $groups[$rider->id] = [
                        'rider' => $rider,
                        'seller_orders' => [],
                    ];
                }

                $groups[$rider->id]['seller_orders'][] = [
                    'seller_order' => $sellerOrder,
                    'address' => $address,
                ];
            }

            if (empty($groups)) {
                throw new RuntimeException(
                    'No available riders were found near the sellers.'
                );
            }

            $deliveries = [];

            foreach ($groups as $group) {
                $delivery = Delivery::create([
                    'order_id' => $orderId,
                    'rider_id' => $group['rider']->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                $sequence = 1;

                foreach ($group['seller_orders'] as $item) {
                    $sellerOrder = $item['seller_order'];
                    $address = $item['address'];

                    Deliveries_stop::create([
                        'delivery_id' => $delivery->id,
                        'seller_order_id' => $sellerOrder->id,
                        'stop_type' => 'pickup',
                        'sequence' => $sequence++,
                        'address' => $this->formatAddress($address),
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'status' => 'pending',
                    ]);
                }

                $deliveries[] = $delivery->load([
                    'rider',
                    'deliveries_stops',
                ]);
            }

            return $deliveries;
        });
    }

    protected function findBestRider(
        $nearbyRiders,
        array $groups
    ): ?Rider {
        foreach ($nearbyRiders as $rider) {
            if (isset($groups[$rider->id])) {
                return $rider;
            }
        }

        return $nearbyRiders->first();
    }

    protected function formatAddress($address): string
    {
        return collect([
            $address->address_line,
            $address->district,
            $address->city,
            $address->region,
            $address->country,
        ])
            ->filter()
            ->implode(', ');
    }
}