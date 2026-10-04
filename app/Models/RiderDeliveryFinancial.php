<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Financial ledger for the rider assigned to one OTP-verified delivery. */
class RiderDeliveryFinancial extends Model
{
    protected $fillable = [
        'delivery_id', 'delivery_fee', 'commission_rate', 'commission_amount',
        'rider_payout_amount', 'payout_status', 'provider_reference',
        'provider_transaction_id', 'payout_attempts', 'paid_at', 'failure_reason',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'rider_payout_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
