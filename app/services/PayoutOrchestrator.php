<?php

namespace App\Services;

use App\Jobs\ProcessClickPesaPayout;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\RiderDeliveryFinancial;
use App\Models\SellerOrderFinancial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Queue recipient-specific payouts only after payment and delivery verification. */
class PayoutOrchestrator
{
    private const DELIVERY_COMMISSION_RATE = 0.0025;

    public function queueEligibleForOrder(Order $order): void
    {
        $order->loadMissing('payments');
        if (! $order->payments->contains(fn ($payment): bool => $payment->status === 'paid')) {
            return;
        }

        $order->deliveries()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereHas('otp', fn ($query) => $query->whereNotNull('verified_at'))
            ->with('deliveries_stops.sellerOrder.financial')
            ->each(fn (Delivery $delivery) => $this->queueEligibleForDelivery($delivery));
    }

    public function queueEligibleForUser(User $user): void
    {
        Delivery::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereHas('otp', fn ($query) => $query->whereNotNull('verified_at'))
            ->whereHas('order.payments', fn ($query) => $query->where('status', 'paid'))
            ->with('order.payments', 'deliveries_stops.sellerOrder.financial')
            ->each(function (Delivery $delivery) use ($user): void {
                if ((int) $delivery->rider?->user_id === (int) $user->id
                    || $delivery->deliveries_stops->contains(
                        fn ($stop): bool => (int) $stop->sellerOrder?->seller_id === (int) $user->id
                    )) {
                    $this->queueEligibleForDelivery($delivery);
                }
            });
    }

    public function queueEligibleForDelivery(Delivery $delivery): void
    {
        $delivery->loadMissing('order.payments', 'otp', 'rider.user.payoutDestination', 'deliveries_stops.sellerOrder.financial');

        if ($delivery->status !== 'completed' || ! $delivery->completed_at || ! $delivery->otp?->verified_at) {
            return;
        }
        if (! $delivery->order?->payments?->contains(fn ($payment): bool => $payment->status === 'paid')) {
            return;
        }

        $sellerOrders = $delivery->deliveries_stops
            ->pluck('sellerOrder')
            ->filter()
            ->unique('id');

        foreach ($sellerOrders as $sellerOrder) {
            $financial = $sellerOrder->financial;
            $seller = $sellerOrder->seller;
            $destination = $seller?->payoutDestination;
            if ($financial && $destination) {
                $this->queue('seller', $financial, (int) $seller->id, $destination);
            }
        }

        $rider = $delivery->rider;
        $deliveryFees = (float) $sellerOrders->sum(fn ($sellerOrder): float => (float) $sellerOrder->delivery_fee);
        $commission = round($deliveryFees * self::DELIVERY_COMMISSION_RATE, 2);
        $financial = RiderDeliveryFinancial::firstOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'delivery_fee' => $deliveryFees,
                'commission_rate' => self::DELIVERY_COMMISSION_RATE,
                'commission_amount' => $commission,
                'rider_payout_amount' => round(max(0, $deliveryFees - $commission), 2),
                'payout_status' => 'pending',
            ]
        );
        $destination = $rider?->user?->payoutDestination;
        if ($rider && $destination && (float) $financial->rider_payout_amount > 0) {
            $this->queue('rider', $financial, (int) $rider->user_id, $destination);
        }
    }

    private function queue(string $type, SellerOrderFinancial|RiderDeliveryFinancial $financial, int $userId, $destination): void
    {
        $amount = $type === 'seller' ? (float) $financial->seller_payout_amount : (float) $financial->rider_payout_amount;
        if ($amount <= 0) {
            return;
        }
        if (! in_array($destination->payout_method, ['mobile_money', 'bank'], true)) {
            return;
        }
        if ($destination->payout_method === 'mobile_money' && ! $destination->verified_at) {
            return;
        }
        if ($destination->payout_method === 'bank'
            && (! $destination->bank_account_number || ! $destination->bank_account_name || ! $destination->bank_bic)) {
            return;
        }

        DB::transaction(function () use ($type, $financial, $userId): void {
            $locked = $financial->newQuery()->lockForUpdate()->find($financial->id);
            if (! $locked || in_array($locked->payout_status, ['queued', 'processing', 'paid'], true)) {
                return;
            }
            $attempt = (int) $locked->payout_attempts + 1;
            $reference = substr('OM'.strtoupper($type[0]).$locked->getKey().'A'.$attempt, 0, 20);
            $locked->update([
                'payout_status' => 'queued',
                'provider_reference' => $reference,
                'payout_attempts' => $attempt,
                'failure_reason' => null,
            ]);
            ProcessClickPesaPayout::dispatch($type, (int) $locked->getKey(), $userId)
                ->onQueue('payouts')->afterCommit();
        });
    }
}
