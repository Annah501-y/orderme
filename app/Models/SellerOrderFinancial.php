<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOrderFinancial extends Model
{
    protected $fillable = [
        'seller_order_id',
        'seller_total',
        'commission_rate',
        'commission_amount',
        'seller_payout_amount',
        'payout_status',
        'provider_reference',
        'paid_at',
        'failure_reason',
    ];

    protected $casts = [
        'seller_total' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'seller_payout_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
