<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'status',
        'seller_total',
    ];

    protected $casts = [
        'seller_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
    public function deliveries_stops():HasMany
    {
        return $this->hasMany(Deliveries_stop::class);
    }
}