<?php

namespace App\Models;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
        'status',
    ];
    
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }
   

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
}
