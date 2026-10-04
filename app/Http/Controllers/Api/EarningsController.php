<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\SellerOrderFinancial;
use App\Models\RiderDeliveryFinancial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EarningsController extends Controller
{
    /** Return the authenticated seller's or rider's verified earnings ledger. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('rider')) {
            return $this->riderEarnings($user);
        }

        if ($user->hasRole('seller')) {
            return $this->sellerEarnings($user);
        }

        return response()->json([
            'success' => false,
            'message' => 'Only sellers and riders can access earnings.',
        ], 403);
    }

    /** Build a delivery ledger from the rider's own OTP-verified completed deliveries. */
    private function riderEarnings(User $user): JsonResponse
    {
        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        $earnings = Delivery::query()
            ->where('rider_id', $rider->id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereHas('otp', fn (Builder $query) => $query->whereNotNull('verified_at'))
            ->with(['financial', 'order.financials', 'otp'])
            ->orderByDesc('completed_at')
            ->get()
            ->map(function (Delivery $delivery): array {
                $financial = $delivery->financial;

                return [
                    'entry_id' => 'delivery-'.$delivery->id,
                    'delivery_id' => $delivery->id,
                    'seller_order_id' => null,
                    'order_id' => $delivery->order_id,
                    'completed_at' => $delivery->completed_at,
                    'otp_verified_at' => $delivery->otp?->verified_at,
                    'payout_amount' => (float) ($financial?->rider_payout_amount ?? 0),
                    'currency' => $delivery->order?->financials?->currency ?? 'TZS',
                    'payout_status' => $financial?->payout_status ?? 'pending',
                    'provider_reference' => $financial?->provider_reference,
                ];
            });

        return $this->earningsResponse('rider', $earnings);
    }

    /** Build a seller ledger after the rider assigned to that seller order verifies delivery. */
    private function sellerEarnings(User $user): JsonResponse
    {
        $financials = SellerOrderFinancial::query()
            ->whereHas('sellerOrder', function (Builder $query) use ($user): void {
                $query->where('seller_id', $user->id)
                    ->whereHas('deliveries_stops.delivery', function (Builder $deliveryQuery): void {
                        $deliveryQuery->where('status', 'completed')
                            ->whereNotNull('completed_at')
                            ->whereHas('otp', fn (Builder $otpQuery) => $otpQuery->whereNotNull('verified_at'));
                    })
                    // A split order releases this seller's amount only after its own rider verifies the parcel.
                    ->whereDoesntHave('deliveries_stops.delivery', function (Builder $deliveryQuery): void {
                        $deliveryQuery->where(function (Builder $statusQuery): void {
                            $statusQuery->where('status', '!=', 'completed')
                                ->orWhereNull('completed_at');
                        })->orWhereDoesntHave('otp', fn (Builder $otpQuery) => $otpQuery->whereNotNull('verified_at'));
                    });
            })
            ->with([
                'sellerOrder.order.financials',
                'sellerOrder.deliveries_stops.delivery.otp',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $earnings = $financials->map(function (SellerOrderFinancial $financial): array {
            $sellerOrder = $financial->sellerOrder;
            $order = $sellerOrder?->order;
            $delivery = $sellerOrder?->deliveries_stops
                ?->pluck('delivery')
                ->filter(fn (?Delivery $assignedDelivery): bool => $assignedDelivery?->status === 'completed'
                    && $assignedDelivery->completed_at !== null
                    && $assignedDelivery->otp?->verified_at !== null)
                ->sortByDesc('completed_at')
                ->first();

            return [
                'entry_id' => 'seller-order-'.$financial->seller_order_id,
                'delivery_id' => $delivery?->id,
                'seller_order_id' => $financial->seller_order_id,
                'order_id' => $sellerOrder?->order_id,
                'completed_at' => $delivery?->completed_at,
                'otp_verified_at' => $delivery?->otp?->verified_at,
                'payout_amount' => (float) $financial->seller_payout_amount,
                'currency' => $order?->financials?->currency ?? 'TZS',
                'payout_status' => $financial->payout_status,
                'provider_reference' => $financial->provider_reference,
            ];
        })->values();

        return $this->earningsResponse('seller', $earnings);
    }

    /**
     * Keep the summary and ledger response consistent for both account types.
     *
     * @param  Collection<int, array<string, float|int|string|null>>  $earnings
     */
    private function earningsResponse(string $role, Collection $earnings): JsonResponse
    {
        $totalEarned = $earnings->sum('payout_amount');
        $pendingPayout = $earnings
            ->reject(fn (array $earning): bool => $earning['payout_status'] === 'paid')
            ->sum('payout_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'summary' => [
                    'completed_count' => $earnings->count(),
                    'total_earned' => round($totalEarned, 2),
                    'pending_payout' => round($pendingPayout, 2),
                    'currency' => $earnings->first()['currency'] ?? 'TZS',
                ],
                'earnings' => $earnings->values(),
            ],
        ]);
    }
}
