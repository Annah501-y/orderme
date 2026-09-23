<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\SellerOrder;
use App\Models\SellerOrderFinancial;
use Illuminate\Support\Facades\DB;

class OrderFinancialService
{
    private const SELLER_COMMISSION_RATE = 0.01;

    private const DELIVERY_COMMISSION_RATE = 0.0025;

    public function calculate(Order $order): OrderFinancial
    {
        return DB::transaction(function () use ($order) {

            $sellerCommissionTotal = 0;
            $sellerPayoutTotal = 0;

            $sellerOrders = SellerOrder::where(
                'order_id',
                $order->id
            )->get();

            foreach ($sellerOrders as $sellerOrder) {
                $sellerTotal = (float)
                $sellerOrder->seller_total;
                $commission = round($sellerTotal * self::SELLER_COMMISSION_RATE, 2);
                $sellerPayout = round(
                    $sellerTotal - $commission, 2
                );
                SellerOrderFinancial::updateOrCreate(
                    [
                        'seller_order_id' => $sellerOrder->id,
                    ],
                    [
                        'seller_total' => $sellerTotal,
                        'commission_rate' => self::SELLER_COMMISSION_RATE,
                        'commission_amount' => $commission,
                        'seller_payout_amount' => $sellerPayout,
                        'payout_status' => 'pending',
                    ]
                );
                $sellerCommissionTotal += $commission;
                $sellerPayoutTotal += $sellerPayout;
            }

            $deliveryFee = (float) ($order->delivery_fee ?? 0);

            $deliveryCommission = round(
                $deliveryFee * self::DELIVERY_COMMISSION_RATE,
                2
            );

            $riderPayout = round(
                $deliveryFee - $deliveryCommission,
                2
            );

            $orderMeCommission = round(
                $sellerCommissionTotal + $deliveryCommission,
                2
            );

            $financial = OrderFinancial::updateOrCreate(
                [
                    'order_id' => $order->id,
                ],
                [
                    'seller_commission_total' => $sellerCommissionTotal,

                    'delivery_fee' => $deliveryFee,

                    'delivery_commission' => $deliveryCommission,

                    'orderme_commission_total' => $orderMeCommission,

                    'seller_payout_total' => $sellerPayoutTotal,

                    'rider_payout_total' => $riderPayout,

                    'order_total' => $order->total_amount,

                    'currency' => $order->currency ?? 'TZS',

                    'status' => 'pending',
                ]
            );

            return $financial;
        });
    }
}
