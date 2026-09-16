<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'rider_id',
        'status',
        'current_latitude',
        'current_longitude',
        'assigned_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];
    protected $cast = [
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'location_updated_at' => 'datetime',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
    public function order():BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function rider():BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
    public function deliveries_stops():HasMany
    {
        return $this->hasMany(Deliveries_stop::class)->orderBy('sequence');
    }
}
