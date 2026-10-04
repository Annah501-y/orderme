<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RiderDeliveryFinancial;
use App\Models\SellerOrderFinancial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provide administrators with searchable customer payments and seller/rider payouts. */
class AdminPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:payments,seller_payouts,rider_payouts'],
            'status' => ['nullable', 'in:pending,queued,processing,paid,failed'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $validated['type'] ?? 'payments';
        [$query, $amountColumn, $statusColumn] = match ($type) {
            'seller_payouts' => [
                SellerOrderFinancial::query()->with(['sellerOrder.seller.payoutDestination', 'sellerOrder.order']),
                'seller_payout_amount',
                'payout_status',
            ],
            'rider_payouts' => [
                RiderDeliveryFinancial::query()->with(['delivery.rider.user.payoutDestination', 'delivery.order']),
                'rider_payout_amount',
                'payout_status',
            ],
            default => [Payment::query()->with(['order.user']), 'amount', 'status'],
        };

        $query->latest();

        if (! empty($validated['status'])) {
            $query->where($statusColumn, $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($recordQuery) use ($search, $type): void {
                if ($type === 'payments') {
                    $recordQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($orderQuery) use ($search): void {
                            $orderQuery->where('guest_name', 'like', "%{$search}%")
                                ->orWhere('guest_phone', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($userQuery) use ($search): void {
                                    $userQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                    if (is_numeric($search)) $recordQuery->orWhere('order_id', (int) $search);
                } elseif ($type === 'seller_payouts') {
                    $recordQuery->where('provider_reference', 'like', "%{$search}%")
                        ->orWhere('provider_transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('sellerOrder.seller', function ($sellerQuery) use ($search): void {
                            $sellerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    if (is_numeric($search)) {
                        $recordQuery->orWhereHas('sellerOrder', function ($sellerOrderQuery) use ($search): void {
                            $sellerOrderQuery->where('id', (int) $search)
                                ->orWhere('order_id', (int) $search);
                        });
                    }
                } else {
                    $recordQuery->where('provider_reference', 'like', "%{$search}%")
                        ->orWhere('provider_transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('delivery.rider.user', function ($riderQuery) use ($search): void {
                            $riderQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    if (is_numeric($search)) {
                        $recordQuery->orWhereHas('delivery', function ($deliveryQuery) use ($search): void {
                            $deliveryQuery->where('id', (int) $search)
                                ->orWhere('order_id', (int) $search);
                        });
                    }
                }
            });
        }

        $records = $query->paginate(25)->withQueryString();
        $summary = $query->getModel()->newQuery()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN {$statusColumn} = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$statusColumn} = 'paid' THEN {$amountColumn} ELSE 0 END), 0) as paid_amount")
            ->selectRaw("SUM(CASE WHEN {$statusColumn} IN ('pending', 'queued', 'processing') THEN 1 ELSE 0 END) as awaiting_count")
            ->selectRaw("SUM(CASE WHEN {$statusColumn} = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->first();

        // Normalize the three record types so the admin table can share one renderer.
        $normalizedRecords = $records->getCollection()->map(function ($record) use ($type): array {
            if ($type === 'seller_payouts') {
                $sellerOrder = $record->sellerOrder;
                $recipient = $sellerOrder?->seller;

                return [
                    'id' => $record->id,
                    'order_id' => $sellerOrder?->order_id,
                    'related_id' => $sellerOrder?->id,
                    'recipient_name' => $recipient?->name ?? 'Unknown seller',
                    'recipient_contact' => $recipient?->phone ?? $recipient?->email,
                    'method' => $recipient?->payoutDestination?->payout_method,
                    'provider' => 'ClickPesa',
                    'amount' => $record->seller_payout_amount,
                    'currency' => 'TZS',
                    'status' => $record->payout_status,
                    'reference' => $record->provider_reference,
                    'transaction_id' => $record->provider_transaction_id,
                    'paid_at' => $record->paid_at,
                    'updated_at' => $record->updated_at,
                    'failure_reason' => $record->failure_reason,
                ];
            }

            if ($type === 'rider_payouts') {
                $delivery = $record->delivery;
                $riderUser = $delivery?->rider?->user;

                return [
                    'id' => $record->id,
                    'order_id' => $delivery?->order_id,
                    'related_id' => $delivery?->id,
                    'recipient_name' => $riderUser?->name ?? 'Unknown rider',
                    'recipient_contact' => $riderUser?->phone ?? $riderUser?->email,
                    'method' => $riderUser?->payoutDestination?->payout_method,
                    'provider' => 'ClickPesa',
                    'amount' => $record->rider_payout_amount,
                    'currency' => 'TZS',
                    'status' => $record->payout_status,
                    'reference' => $record->provider_reference,
                    'transaction_id' => $record->provider_transaction_id,
                    'paid_at' => $record->paid_at,
                    'updated_at' => $record->updated_at,
                    'failure_reason' => $record->failure_reason,
                ];
            }

            return [
                'id' => $record->id,
                'order_id' => $record->order_id,
                'related_id' => null,
                'recipient_name' => $record->order?->user?->name ?? $record->order?->guest_name ?? 'Unknown customer',
                'recipient_contact' => $record->order?->user?->phone ?? $record->order?->guest_phone,
                'method' => $record->method,
                'provider' => $record->provider,
                'amount' => $record->amount,
                'currency' => $record->currency,
                'status' => $record->status,
                'reference' => $record->reference,
                'transaction_id' => $record->transaction_id,
                'paid_at' => $record->paid_at,
                'updated_at' => $record->updated_at,
                'failure_reason' => null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'records' => $normalizedRecords,
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'total' => $records->total(),
                ],
                'summary' => $summary,
            ],
        ]);
    }
}
