<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'phone',
        'nida_number',
        'tin_reference',
        'business_license_path',
        'status',
        'rejection_reason',
    ];

    protected $hidden = [
        'business_license_path',
    ];

    protected function casts(): array
    {
        return [
            'nida_number' => 'encrypted',
            'tin_reference' => 'encrypted',
        ];
    }

    /**
     * The user who owns this seller profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
