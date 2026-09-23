<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFinancial extends Model
{
    protected $fillable = [
        'order_id',
        'seller_commission_total',
        'delivery_commission',
        'orderme_commission_total',
        'seller_payout_total',
        'rider_payout_total',
        'delivery_fee',
        'order_total',
        'currency',
        'status',
    ];

    protected $casts = [
        'seller_commission_total' => 'decimal:2',
        'delivery_commission' => 'decimal:2',
        'orderme_commission_total' => 'decimal:2',
        'seller_payout_total' => 'decimal:2',
        'rider_payout_total' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'order_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
