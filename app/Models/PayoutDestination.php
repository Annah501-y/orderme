<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutDestination extends Model
{
    protected $fillable = [
        'payout_method',
        'mobile_phone',
        'bank_bic',
        'bank_account_number',
        'bank_account_name',
    ];

    protected $hidden = [
        'mobile_phone',
        'bank_bic',
        'bank_account_number',
        'bank_account_name',
    ];

    protected function casts(): array
    {
        return [
            'mobile_phone' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'bank_account_name' => 'encrypted',
            'verified_at' => 'datetime',
            'verification_sent_at' => 'datetime',
            'verification_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
