<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deliveries_stop extends Model
{
    protected $fillable = [
        'delivery_id',
        'seller_order_id',
        'stop_type',
        'sequence',
        'address',
        'latitude',
        'longitude',
        'status',
        'arrived_at',
        'completed_at'
    ];
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'arrived_at'=> 'datetime',
        'completed_at' => 'datetime',
        'sequence' => 'integer',
    ];
    public function delivery():BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
