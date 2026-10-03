<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Deliveries_stop;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryAssignmentService
{
    private const SELLER_GROUP_RADIUS_KM = 1;

    private const RIDER_SEARCH_RADIUS_KM = 1;

    public function __construct(
        protected RiderLocationService $riderLocationService
    ) {}

    /**
     * Assign ready seller orders for one customer order in nearby pickup groups.
     *
     * @return array<int, Delivery>
     */
    public function assign(int $orderId): array
    {
        if (! Order::query()->whereKey($orderId)->exists()) {
            throw new RuntimeException('Order not found.');
        }

        return DB::transaction(function () use ($orderId): array {
            $sellerOrders = SellerOrder::query()
                ->with([
                    'seller.addresses' => function ($query): void {
                        $query->where('is_default', true)->limit(1);
                    },
                ])
                ->where('order_id', $orderId)
                ->where('status', 'ready_for_delivery')
                ->whereDoesntHave('deliveries_stops')
                ->lockForUpdate()
                ->get();

            if ($sellerOrders->isEmpty()) {
                throw new RuntimeException(
                    'No unassigned seller orders are ready for delivery.'
                );
            }

            $pickupGroups = $this->groupNearbySellerOrders($sellerOrders);

            if (
                $pickupGroups->isEmpty() ||
                $pickupGroups->flatten(1)->count() !== $sellerOrders->count()
            ) {
                throw new RuntimeException(
                    'Every ready seller order needs a default address with GPS coordinates.'
                );
            }

            $assignedRiderIds = [];
            $assignmentGroups = [];

            foreach ($pickupGroups as $pickupGroup) {
                $rider = $this->findNearbyAvailableRider(
                    $pickupGroup,
                    $assignedRiderIds
                );

                if (! $rider) {
                    throw new RuntimeException(
                        'No available rider was found within 1 km for each pickup group.'
                    );
                }

                $assignedRiderIds[] = $rider->id;
                $assignmentGroups[] = [
                    'rider' => $rider,
                    'seller_orders' => $pickupGroup,
                ];
            }

            $deliveries = [];

            foreach ($assignmentGroups as $assignmentGroup) {
                $delivery = Delivery::create([
                    'order_id' => $orderId,
                    'rider_id' => $assignmentGroup['rider']->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($assignmentGroup['seller_orders'] as $sequence => $item) {
                    /** @var SellerOrder $sellerOrder */
                    $sellerOrder = $item['seller_order'];
                    $address = $item['address'];

                    Deliveries_stop::create([
                        'delivery_id' => $delivery->id,
                        'seller_order_id' => $sellerOrder->id,
                        'stop_type' => 'pickup',
                        'sequence' => $sequence + 1,
                        'address' => $this->formatAddress($address),
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'status' => 'pending',
                    ]);

                    $sellerOrder->update(['status' => 'assigned_to_rider']);
                }

                $deliveries[] = $delivery->load([
                    'rider.user',
                    'deliveries_stops.sellerOrder.seller',
                ]);
            }

            return $deliveries;
        });
    }

    /**
     * @param Collection<int, SellerOrder> $sellerOrders
     * @return Collection<int, array<int, array{seller_order: SellerOrder, address: mixed}>>
     */
    private function groupNearbySellerOrders(Collection $sellerOrders): Collection
    {
        $pickupLocations = $sellerOrders
            ->map(function (SellerOrder $sellerOrder): ?array {
                $address = $sellerOrder->seller?->addresses->first();

                if (
                    ! $address ||
                    $address->latitude === null ||
                    $address->longitude === null
                ) {
                    return null;
                }

                return [
                    'seller_order' => $sellerOrder,
                    'address' => $address,
                ];
            })
            ->filter()
            ->values();

        $groups = collect();

        foreach ($pickupLocations as $pickupLocation) {
            $groupIndex = $groups->search(function (array $group) use ($pickupLocation): bool {
                $anchor = $group[0]['address'];

                return $this->distanceInKilometers(
                    (float) $anchor->latitude,
                    (float) $anchor->longitude,
                    (float) $pickupLocation['address']->latitude,
                    (float) $pickupLocation['address']->longitude
                ) <= self::SELLER_GROUP_RADIUS_KM;
            });

            if ($groupIndex === false) {
                $groups->push([$pickupLocation]);
            } else {
                $group = $groups->get($groupIndex);
                $group[] = $pickupLocation;
                $groups->put($groupIndex, $group);
            }
        }

        return $groups;
    }

    /**
     * @param array<int, array{seller_order: SellerOrder, address: mixed}> $pickupGroup
     * @param array<int, int> $excludedRiderIds
     */
    private function findNearbyAvailableRider(
        array $pickupGroup,
        array $excludedRiderIds
    ): ?Rider {
        $nearbyRiders = collect();

        foreach ($pickupGroup as $pickupLocation) {
            $address = $pickupLocation['address'];
            $nearbyRiders = $nearbyRiders->merge(
                $this->riderLocationService->findNearbyRiders(
                    (float) $address->latitude,
                    (float) $address->longitude,
                    self::RIDER_SEARCH_RADIUS_KM
                )
            );
        }

        return $nearbyRiders
            ->unique('id')
            ->reject(fn (Rider $rider): bool => in_array(
                $rider->id,
                $excludedRiderIds,
                true
            ))
            ->sortBy('distance_km')
            ->first();
    }

    private function distanceInKilometers(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): float {
        $latitudeDifference = deg2rad($latitudeB - $latitudeA);
        $longitudeDifference = deg2rad($longitudeB - $longitudeA);
        $distance = sin($latitudeDifference / 2) ** 2
            + cos(deg2rad($latitudeA))
            * cos(deg2rad($latitudeB))
            * sin($longitudeDifference / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($distance), sqrt(1 - $distance));
    }

    private function formatAddress(mixed $address): string
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
