<?php

namespace App\Models;
use App\Enums\SellerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerProfile extends Model {
    use HasFactory;
    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'phone',
        'address',
        'status',
    ];
    protected function casts(): array {
        return[
            'status'=> SellerStatus::class,
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }

}
